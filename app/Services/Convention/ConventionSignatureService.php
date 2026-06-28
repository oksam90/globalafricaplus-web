<?php

namespace App\Services\Convention;

use App\Models\Investment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Étape 6 — orchestre la signature électronique d'une convention via Yousign :
 *   sendForSignature() : PDF → Yousign (création + signataires + activation)
 *   syncStatus()       : interroge Yousign et récupère le PDF signé quand prêt.
 */
class ConventionSignatureService
{
    public function __construct(
        private readonly YousignClient $yousign = new YousignClient(),
        private readonly ConventionGenerator $generator = new ConventionGenerator(),
    ) {}

    /**
     * Envoie la convention d'un investissement à la signature des deux parties.
     * Idempotent : si une demande existe déjà, on ne recrée pas.
     */
    public function sendForSignature(Investment $investment): Investment
    {
        if ($investment->signature_request_id) {
            return $investment; // déjà envoyé
        }
        if (!$this->yousign->isConfigured()) {
            throw new RuntimeException('Signature indisponible : Yousign non configuré.');
        }

        // S'assurer que le PDF existe (génère le contrat si besoin).
        if (!$investment->contract_pdf_path) {
            $this->generator->generateForInvestment($investment);
            $investment->refresh();
        }
        $disk = (string) config('conventions.disk', 'local');
        if (!$investment->contract_pdf_path || !Storage::disk($disk)->exists($investment->contract_pdf_path)) {
            throw new RuntimeException('PDF de la convention introuvable (conversion LibreOffice ?).');
        }

        $investment->loadMissing(['investor', 'project.user']);
        $investor = $investment->investor;
        $owner    = $investment->project?->user;
        if (!$investor?->email || !$owner?->email) {
            throw new RuntimeException('Email manquant pour un des signataires.');
        }

        $pdfBinary = Storage::disk($disk)->get($investment->contract_pdf_path);
        $name = 'Convention ' . ($investment->contract_type ?: $investment->type) . ' — investissement #' . $investment->id;

        // 1) Demande
        $req = $this->yousign->createSignatureRequest($name, 'investment-' . $investment->id);
        $requestId = $req['id'] ?? null;
        if (!$requestId) {
            throw new RuntimeException('Yousign : identifiant de demande manquant.');
        }

        // 2) Document
        $doc = $this->yousign->addDocument($requestId, $pdfBinary, 'convention.pdf');
        $documentId = $doc['id'] ?? null;
        if (!$documentId) {
            throw new RuntimeException('Yousign : identifiant de document manquant.');
        }

        // 3) Signataires (investisseur + porteur)
        $fields = config('yousign.fields');
        $this->yousign->addSigner($requestId, $this->signerPayload($investor->name, $investor->email, $documentId, $fields['investor'], $fields));
        $this->yousign->addSigner($requestId, $this->signerPayload($owner->name, $owner->email, $documentId, $fields['owner'], $fields));

        // 4) Activation (envoi des emails)
        $this->yousign->activate($requestId);

        $investment->forceFill([
            'signature_provider'   => 'yousign',
            'signature_request_id' => $requestId,
            'contract_status'      => 'sent',
            'contract_sent_at'     => now(),
        ])->save();

        return $investment;
    }

    /**
     * Interroge Yousign et, si la signature est terminée, récupère le PDF signé.
     * Retourne le statut Yousign brut (ongoing|done|declined|expired|canceled…).
     */
    public function syncStatus(Investment $investment): string
    {
        if (!$investment->signature_request_id) {
            return 'none';
        }

        $req = $this->yousign->getSignatureRequest($investment->signature_request_id);
        $status = (string) ($req['status'] ?? 'unknown');

        if ($status === 'done' && $investment->contract_status !== 'signed') {
            $documentId = $req['documents'][0]['id'] ?? null;
            if ($documentId) {
                $signed = $this->yousign->downloadDocument($investment->signature_request_id, $documentId);
                $disk = (string) config('conventions.disk', 'local');
                $signedPath = sprintf(
                    '%s/%d/Convention_%s_%d_signe.pdf',
                    trim((string) config('conventions.storage_dir', 'contracts'), '/'),
                    $investment->id,
                    $investment->contract_type ?: $investment->type,
                    $investment->id,
                );
                Storage::disk($disk)->put($signedPath, $signed);

                $investment->forceFill([
                    'contract_signed_path' => $signedPath,
                    'contract_status'      => 'signed',
                    'contract_signed_at'   => now(),
                ])->save();
            }
        } elseif (in_array($status, ['declined', 'expired', 'canceled'], true)) {
            $investment->forceFill(['contract_status' => 'failed'])->save();
        }

        return $status;
    }

    private function signerPayload(string $fullName, string $email, string $documentId, array $pos, array $fields): array
    {
        [$first, $last] = $this->splitName($fullName);

        return [
            'info' => [
                'first_name' => $first,
                'last_name'  => $last,
                'email'      => $email,
                'locale'     => 'fr',
            ],
            'signature_level'               => (string) config('yousign.signature_level', 'electronic_signature'),
            'signature_authentication_mode' => (string) config('yousign.authentication_mode', 'no_otp'),
            'fields' => [[
                'document_id' => $documentId,
                'type'        => 'signature',
                'page'        => (int) ($fields['page'] ?? 1),
                'x'           => (int) $pos['x'],
                'y'           => (int) $pos['y'],
                'width'       => (int) ($fields['width'] ?? 180),
                'height'      => (int) ($fields['height'] ?? 60),
            ]],
        ];
    }

    /** @return array{0:string,1:string} */
    private function splitName(string $fullName): array
    {
        // Yousign n'accepte dans les noms que des lettres (accents inclus),
        // espaces, tirets et apostrophes. On retire le reste (« + », chiffres,
        // symboles…) sinon l'API rejette « unauthorized chars » (HTTP 400).
        $clean = $this->sanitizeName($fullName);
        $parts = preg_split('/\s+/', $clean) ?: [];
        $first = ($parts[0] ?? '') !== '' ? $parts[0] : 'Partie';
        $last  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $first;

        return [$first ?: 'Partie', $last ?: 'Partie'];
    }

    private function sanitizeName(string $value): string
    {
        $value = preg_replace('/[^\p{L}\p{M}\s\'’\-]/u', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
