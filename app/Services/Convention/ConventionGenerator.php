<?php

namespace App\Services\Convention;

use App\Models\Investment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Fabrique automatiquement la convention d'investissement :
 *   1. AIGUILLAGE  — choisit le gabarit selon `investment.type` (config/conventions.php) ;
 *   2. CONTEXTE    — récupère les vraies données (ConventionContext) ;
 *   3. INJECTION   — remplace chaque trou du gabarit (.docx) par la donnée,
 *                    occurrence par occurrence, selon le plan d'injection ;
 *   4. PRODUCTION  — écrit le .docx final dans storage et le rattache à
 *                    l'investissement (status « generated »).
 *
 * Les champs réservés au conseil (taux, durée, nombre de parts, signatures…)
 * restent volontairement en l'état pour relecture juridique.
 */
class ConventionGenerator
{
    /** Marqueur interne : laisser le placeholder intact. */
    private const KEEP = "\0KEEP\0";

    public function __construct(
        private readonly ConventionContext $context = new ConventionContext(),
        private readonly PdfConverter $pdf = new PdfConverter(),
    ) {}

    /**
     * Génère (une seule fois) la convention d'un investissement.
     * Idempotent : si déjà générée, retourne le chemin existant.
     */
    public function generateForInvestment(Investment $investment): string
    {
        if ($investment->contract_path && $investment->contract_status === 'generated') {
            return $investment->contract_path;
        }

        $type = $investment->type ?: 'equity';
        $tpl  = config("conventions.templates.$type");
        if (!$tpl) {
            throw new RuntimeException("Aucun gabarit de convention pour le type « {$type} ».");
        }

        $templatePath = rtrim((string) config('conventions.templates_dir'), '/\\') . DIRECTORY_SEPARATOR . $tpl['file'];
        if (!is_file($templatePath)) {
            throw new RuntimeException("Gabarit introuvable : {$templatePath}");
        }

        // 2) Contexte + 3) plan d'injection (commun + compte de décaissement par type).
        $ctx  = $this->context->build($investment);
        $plan = (array) config('conventions.injection', []);
        $plan[$tpl['payout_placeholder']] = ['payout_account'];

        $queues = $this->buildQueues($plan, $ctx['data'], $ctx['milestones']);

        // Tableaux à hauteur variable (échéancier de versement : 1 à 12 lignes).
        $collections = ['funding_schedule' => $ctx['funding_schedule'] ?? []];

        // 4) Production
        $disk = (string) config('conventions.disk', 'local');
        $dir  = trim((string) config('conventions.storage_dir', 'contracts'), '/');
        $relative = sprintf(
            '%s/%d/Convention_%s_%d_%s.docx',
            $dir,
            $investment->id,
            $type,
            $investment->id,
            Carbon::now()->format('Ymd_His'),
        );

        Storage::disk($disk)->makeDirectory(dirname($relative));
        $absolute = Storage::disk($disk)->path($relative);

        if (!copy($templatePath, $absolute)) {
            throw new RuntimeException('Impossible de copier le gabarit.');
        }

        $this->fillDocx($absolute, $queues, $collections);

        // 5) Conversion PDF (best-effort).
        $pdfRelative = null;
        $pdfAbsolute = $this->pdf->toPdf($absolute);
        if ($pdfAbsolute) {
            $pdfRelative = preg_replace('/\.docx$/i', '.pdf', $relative);
        }

        $investment->forceFill([
            'contract_type'         => $type,
            'contract_path'         => $relative,
            'contract_pdf_path'     => $pdfRelative,
            'contract_status'       => 'generated',
            'contract_generated_at' => now(),
        ])->save();

        return $relative;
    }

    /**
     * Construit, pour chaque placeholder, la file (FIFO) de valeurs résolues,
     * où KEEP signale « laisser tel quel ».
     *
     * @param array<string,array<int,string>> $plan
     * @param array<string,string> $data
     * @param array<int,array<string,string>> $milestones
     * @return array<string,array<int,string>>
     */
    private function buildQueues(array $plan, array $data, array $milestones): array
    {
        $queues = [];
        foreach ($plan as $placeholder => $sources) {
            $queue = [];
            foreach ((array) $sources as $source) {
                $queue[] = $this->resolve($source, $data, $milestones);
            }
            $queues[$placeholder] = $queue;
        }
        return $queues;
    }

    private function resolve(string $source, array $data, array $milestones): string
    {
        if ($source === 'KEEP') {
            return self::KEEP;
        }
        // milestones.{i}.{field}
        if (str_starts_with($source, 'milestones.')) {
            [, $i, $field] = explode('.', $source) + [null, null, null];
            $row = $milestones[(int) $i] ?? null;
            $val = $row[$field] ?? null;
            return ($val === null || $val === '') ? self::KEEP : (string) $val;
        }
        $val = $data[$source] ?? null;
        return ($val === null || $val === '') ? self::KEEP : (string) $val;
    }

    /**
     * Remplace les placeholders dans word/document.xml du .docx, occurrence par
     * occurrence. Chaque placeholder est un run <w:t> atomique.
     *
     * @param array<string,array<int,string>> $queues
     * @param array<string,array<int,array<string,string>>> $collections
     */
    private function fillDocx(string $docxAbsolutePath, array $queues, array $collections = []): void
    {
        $zip = new ZipArchive();
        if ($zip->open($docxAbsolutePath) !== true) {
            throw new RuntimeException('Ouverture du .docx impossible.');
        }

        $xml = $zip->getFromName('word/document.xml');
        if ($xml === false) {
            $zip->close();
            throw new RuntimeException('document.xml introuvable dans le .docx.');
        }

        // Les lignes répétables sont dupliquées AVANT la passe de remplacement
        // ordonnée : elles portent leurs propres placeholders, distincts de
        // ceux du plan d'injection, et n'en décalent donc pas les compteurs.
        $xml = $this->expandRepeatRows($xml, $collections);

        $counters = [];
        $newXml = preg_replace_callback(
            '/(<w:t\b[^>]*>)(.*?)(<\/w:t>)/su',
            function ($m) use ($queues, &$counters) {
                $open = $m[1];
                $inner = $m[2];
                $close = $m[3];

                $decoded = trim(html_entity_decode($inner, ENT_QUOTES | ENT_XML1, 'UTF-8'));
                if (!isset($queues[$decoded])) {
                    return $m[0]; // placeholder non planifié → intact
                }

                $idx = $counters[$decoded] ?? 0;
                $queue = $queues[$decoded];
                if ($idx >= count($queue)) {
                    return $m[0]; // séquence épuisée → occurrences restantes intactes
                }
                $counters[$decoded] = $idx + 1;

                $value = $queue[$idx];
                if ($value === self::KEEP) {
                    return $m[0];
                }
                $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
                return $open . $escaped . $close;
            },
            $xml,
        );

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $newXml);
        $zip->close();
    }

    /**
     * Duplique les lignes de tableau à hauteur variable.
     *
     * Le gabarit ne contient qu'UNE ligne modèle par tableau répétable,
     * reconnue à son placeholder « marqueur ». Elle est clonée autant de fois
     * qu'il y a d'éléments dans la collection (l'échéancier de versement compte
     * de 1 à 12 lignes selon le fractionnement choisi), chaque clone recevant
     * les valeurs de son élément.
     *
     * @param array<string,array<int,array<string,string>>> $collections
     */
    private function expandRepeatRows(string $xml, array $collections): string
    {
        foreach ((array) config('conventions.repeat', []) as $key => $spec) {
            $rows   = $collections[$key] ?? [];
            $marker = (string) ($spec['marker'] ?? '');
            $fields = (array) ($spec['fields'] ?? []);

            if ($rows === [] || $marker === '' || $fields === []) {
                continue; // Rien à répéter : la ligne modèle reste en l'état.
            }

            // Ligne modèle : le premier <w:tr> qui contient le marqueur.
            if (!preg_match_all('/<w:tr\b[^>]*>.*?<\/w:tr>/su', $xml, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $template = null;
            $offset   = null;
            foreach ($matches[0] as [$candidate, $at]) {
                if (str_contains($candidate, htmlspecialchars($marker, ENT_QUOTES | ENT_XML1, 'UTF-8'))
                    || str_contains($candidate, $marker)) {
                    $template = $candidate;
                    $offset   = $at;
                    break;
                }
            }

            if ($template === null) {
                continue;
            }

            $rendered = '';
            foreach ($rows as $row) {
                $rendered .= $this->renderRow($template, $fields, $row);
            }

            $xml = substr_replace($xml, $rendered, $offset, strlen($template));
        }

        return $xml;
    }

    /**
     * @param array<string,string> $fields  placeholder → clé de la ligne
     * @param array<string,string> $row
     */
    private function renderRow(string $template, array $fields, array $row): string
    {
        return preg_replace_callback(
            '/(<w:t\b[^>]*>)(.*?)(<\/w:t>)/su',
            function ($m) use ($fields, $row) {
                $decoded = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_XML1, 'UTF-8'));
                $field   = $fields[$decoded] ?? null;

                if ($field === null || !array_key_exists($field, $row)) {
                    return $m[0];
                }

                return $m[1] . htmlspecialchars((string) $row[$field], ENT_QUOTES | ENT_XML1, 'UTF-8') . $m[3];
            },
            $template,
        );
    }
}
