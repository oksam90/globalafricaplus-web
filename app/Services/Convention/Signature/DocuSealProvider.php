<?php

namespace App\Services\Convention\Signature;

use App\Models\Investment;
use App\Services\Convention\ConventionContext;
use App\Services\Convention\DocuSealClient;
use RuntimeException;

/**
 * Signature électronique via DocuSeal auto-hébergé.
 *
 * On utilise `POST /api/submissions/pdf` : le PDF de la convention (produit par
 * ConventionGenerator + LibreOffice) est transmis tel quel, les emplacements de
 * signature étant posés par coordonnées. Aucun gabarit permanent n'est créé
 * dans DocuSeal, et les modèles .docx validés juridiquement n'ont pas à être
 * modifiés pour y insérer des balises {{...}}.
 */
class DocuSealProvider implements SignatureProviderInterface
{
    public function __construct(
        private readonly DocuSealClient $client = new DocuSealClient(),
    ) {}

    /**
     * Dénomination des deux parties, PAR TYPE de convention.
     *
     * Chaque modèle nomme ses parties différemment (Prêteur/Emprunteur,
     * Donateur/Bénéficiaire…). Les rôles envoyés à DocuSeal doivent porter
     * exactement le libellé des rôles du gabarit, sans quoi les champs ne sont
     * rattachés à personne et rien n'est prérempli.
     *
     * @return array{investor:string,owner:string}
     */
    private function roles(Investment $investment): array
    {
        $type = (string) ($investment->contract_type ?: $investment->type);

        return [
            'investor' => (string) (config("conventions.templates.{$type}.party")
                ?: config('signature.roles.investor', 'Investisseur')),
            'owner' => (string) (config("conventions.templates.{$type}.counterparty")
                ?: config('signature.roles.owner', 'Porteur de projet')),
        ];
    }

    public function name(): string
    {
        return 'docuseal';
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function requiresDocument(): bool
    {
        // Mode « gabarit » : le document est déjà dans DocuSeal, on n'envoie
        // que les valeurs. Seul le mode « one-off » transmet le PDF.
        return config('docuseal.mode', 'template') === 'oneoff';
    }

    public function send(Investment $investment, string $pdfBinary, string $documentName, array $signers): array
    {
        return config('docuseal.mode', 'template') === 'oneoff'
            ? $this->sendOneOff($investment, $pdfBinary, $documentName, $signers)
            : $this->sendFromTemplate($investment, $documentName, $signers);
    }

    /**
     * Mode « gabarit » — seul disponible en édition libre.
     *
     * Le document vit dans DocuSeal (un gabarit par type de convention, préparé
     * une fois dans l'interface) ; on ne transmet que les signataires et les
     * valeurs à préremplir.
     */
    private function sendFromTemplate(Investment $investment, string $documentName, array $signers): array
    {
        $type       = (string) ($investment->contract_type ?: $investment->type);
        $templateId = config("docuseal.templates.{$type}");

        if (!$templateId) {
            throw new RuntimeException(
                "Aucun gabarit DocuSeal configuré pour le type « {$type} ». "
                . "Créez-le dans l'interface puis renseignez DOCUSEAL_TEMPLATE_"
                . strtoupper($type) . ' (php artisan docuseal:templates).'
            );
        }

        $sendEmail = (bool) config('docuseal.send_email', false);
        $values    = $this->values($investment);
        $roles     = $this->roles($investment);

        $payload = array_filter([
            'template_id' => (int) $templateId,
            'name'        => $documentName,
            'send_email'  => $sendEmail,
            'send_sms'    => false,
            'order'       => (string) config('docuseal.order', 'random'),
            'completed_redirect_url' => config('docuseal.completed_redirect_url'),
            'expire_at'   => $this->expiresAt(),
            'submitters'  => [
                $this->submitter($signers['investor'], $roles['investor'], $investment, $sendEmail, $values),
                $this->submitter($signers['owner'], $roles['owner'], $investment, $sendEmail, $values),
            ],
        ], static fn ($v) => $v !== null);

        return $this->normalize($this->client->createSubmission($payload), $roles);
    }

    /**
     * Mode « one-off » — édition Pro. Le PDF déjà rempli est envoyé tel quel,
     * aucun gabarit à maintenir dans DocuSeal.
     */
    private function sendOneOff(Investment $investment, string $pdfBinary, string $documentName, array $signers): array
    {
        $sendEmail = (bool) config('docuseal.send_email', false);
        $roles     = $this->roles($investment);

        $payload = array_filter([
            'name'       => $documentName,
            'send_email' => $sendEmail,
            'send_sms'   => false,
            'order'      => (string) config('docuseal.order', 'random'),
            'flatten'    => (bool) config('docuseal.flatten', true),
            'completed_redirect_url' => config('docuseal.completed_redirect_url'),
            'expire_at'  => $this->expiresAt(),
            'documents'  => [[
                'name'   => 'convention',
                'file'   => base64_encode($pdfBinary),
                'fields' => $this->fields($roles),
            ]],
            'submitters' => [
                $this->submitter($signers['investor'], $roles['investor'], $investment, $sendEmail),
                $this->submitter($signers['owner'], $roles['owner'], $investment, $sendEmail),
            ],
        ], static fn ($v) => $v !== null);

        return $this->normalize($this->client->createSubmissionFromPdf($payload), $roles);
    }

    /**
     * Extrait l'identifiant de soumission et les liens de signature.
     *
     * `POST /submissions` renvoie directement le TABLEAU des signataires,
     * tandis que `POST /submissions/pdf` renvoie un objet soumission qui les
     * contient : on gère les deux formes.
     */
    private function normalize(array $response, array $roles): array
    {
        $submitters = isset($response['submitters'])
            ? $response['submitters']
            : (isset($response[0]) ? $response : []);

        $submissionId = $response['id'] ?? ($submitters[0]['submission_id'] ?? null);
        if (!$submissionId) {
            throw new RuntimeException('DocuSeal : identifiant de demande manquant dans la réponse.');
        }

        // Liens de signature directs — indispensables tant que le SMTP de
        // DocuSeal n'est pas configuré, puisqu'aucun email n'est alors envoyé.
        //
        // Ils sont indexés sur une clé INTERNE (investor / owner) et non sur le
        // libellé du rôle : celui-ci change d'un type de convention à l'autre,
        // et le modèle n'a pas à connaître ces libellés pour retrouver le lien
        // de l'utilisateur courant.
        $byLabel  = array_flip($roles);
        $signUrls = [];
        foreach ($submitters as $submitter) {
            $url = $submitter['embed_src'] ?? null;
            $key = $byLabel[$submitter['role'] ?? ''] ?? null;
            if ($url && $key) {
                $signUrls[$key] = $url;
            }
        }

        return [
            'request_id' => (string) $submissionId,
            'sign_urls'  => $signUrls,
            'raw'        => ['status' => $response['status'] ?? null],
        ];
    }

    /**
     * Valeurs à préremplir dans le gabarit : le contexte de convention, aplati
     * en clés simples. Les noms de champs du gabarit DocuSeal doivent
     * correspondre à ces clés.
     */
    private function values(Investment $investment): array
    {
        $context = app(ConventionContext::class)->build($investment);

        // `build()` renvoie trois blocs : les données plates, le tableau des
        // Jalons et l'échéancier de versement. Aplatir le premier est
        // indispensable — sans cela, seuls les jalons étaient préremplis.
        $values = [];
        foreach ((array) ($context['data'] ?? []) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $values[$key] = (string) $value;
            }
        }

        foreach (array_values((array) ($context['milestones'] ?? [])) as $i => $row) {
            $n = $i + 1;
            $values["jalon_{$n}_desc"]    = (string) ($row['desc'] ?? '');
            $values["jalon_{$n}_montant"] = (string) ($row['amount'] ?? '');
            $values["jalon_{$n}_date"]    = (string) ($row['date'] ?? '');
        }

        // Échéancier de versement : le gabarit DocuSeal a une mise en page
        // FIXE, il ne peut pas cloner de lignes. Le modèle prévoit donc les 12
        // lignes du maximum autorisé par le popup d'investissement ; les lignes
        // non utilisées restent vides.
        foreach (array_values((array) ($context['funding_schedule'] ?? [])) as $i => $row) {
            $n = $i + 1;
            if ($n > 12) {
                break;
            }
            $values["ech_{$n}_date"]    = (string) ($row['date'] ?? '');
            $values["ech_{$n}_montant"] = (string) ($row['amount'] ?? '');
            $values["ech_{$n}_cumul"]   = (string) ($row['cumulative'] ?? '');
        }

        return array_filter($values, static fn ($v) => $v !== '');
    }

    public function status(Investment $investment): array
    {
        if (!$investment->signature_request_id) {
            return ['status' => 'unknown', 'raw' => 'none'];
        }

        $submission = $this->client->getSubmission($investment->signature_request_id);
        $raw = (string) ($submission['status'] ?? 'unknown');

        return [
            // Statuts DocuSeal : pending | completed | declined | expired
            'status' => match ($raw) {
                'completed' => 'completed',
                'declined'  => 'declined',
                'expired'   => 'expired',
                'pending'   => 'pending',
                default     => 'unknown',
            },
            'raw' => $raw,
        ];
    }

    public function downloadSigned(Investment $investment): ?string
    {
        if (!$investment->signature_request_id) {
            return null;
        }

        // `merge=true` : un seul PDF même si la convention compte plusieurs
        // documents. Les fichiers renvoyés sont les versions SIGNÉES une fois
        // la demande complétée.
        $documents = $this->client->getSubmissionDocuments($investment->signature_request_id, true);

        $url = $documents['documents'][0]['url'] ?? null;

        return $url ? $this->client->downloadFile($url) : null;
    }

    // ─────────────────────────── helpers ───────────────────────────

    /**
     * Emplacements de signature, en fraction de page (0 → 1).
     * Un champ « signature » et un champ « date » par partie.
     */
    private function fields(array $roles): array
    {
        $conf = config('docuseal.fields');
        $page = (int) ($conf['page'] ?? 1);

        $fields = [];

        foreach (['investor', 'owner'] as $party) {
            $pos  = $conf[$party];
            $role = $roles[$party];

            $fields[] = [
                'name'     => "Signature {$role}",
                'type'     => 'signature',
                'role'     => $role,
                'required' => true,
                'areas'    => [[
                    'page' => $page,
                    'x'    => (float) $pos['x'],
                    'y'    => (float) $pos['y'],
                    'w'    => (float) $conf['width'],
                    'h'    => (float) $conf['height'],
                ]],
            ];

            $fields[] = [
                'name'     => "Date {$role}",
                'type'     => 'date',
                'role'     => $role,
                'required' => true,
                'areas'    => [[
                    'page' => $page,
                    'x'    => (float) $pos['x'],
                    'y'    => (float) $pos['y'] + (float) $conf['date_offset'],
                    'w'    => (float) $conf['width'],
                    'h'    => (float) $conf['date_height'],
                ]],
            ];
        }

        return $fields;
    }

    /**
     * @param  array{name:string,email:string}  $signer
     */
    private function submitter(array $signer, string $role, Investment $investment, bool $sendEmail, array $values = []): array
    {
        return array_filter([
            'name'        => $signer['name'],
            'email'       => $signer['email'],
            'role'        => $role,
            'send_email'  => $sendEmail,
            // Préremplissage (mode « gabarit ») : les clés doivent porter le
            // nom exact des champs définis dans le gabarit DocuSeal.
            'values'      => $values ?: null,
            // Permet de retrouver l'investissement depuis un webhook DocuSeal.
            'external_id' => 'investment-' . $investment->id,
            'metadata'    => [
                'investment_id' => (string) $investment->id,
                'project_id'    => (string) ($investment->project_id ?? ''),
            ],
        ], static fn ($v) => $v !== null && $v !== '');
    }

    private function expiresAt(): ?string
    {
        $days = (int) config('docuseal.expire_after_days', 0);

        return $days > 0 ? now()->addDays($days)->utc()->format('Y-m-d H:i:s') . ' UTC' : null;
    }
}
