<?php

namespace App\Services\Convention\Signature;

use App\Models\Investment;
use App\Services\Convention\YousignClient;
use RuntimeException;

/**
 * Signature électronique via Yousign (API v3).
 *
 * CONSERVÉ après la bascule vers DocuSeal : réactivable par
 * SIGNATURE_PROVIDER=yousign, et toujours utilisé pour suivre les conventions
 * déjà envoyées chez Yousign avant la bascule.
 *
 * La logique provient de l'ancien ConventionSignatureService, déplacée ici sans
 * changement fonctionnel.
 */
class YousignProvider implements SignatureProviderInterface
{
    public function __construct(
        private readonly YousignClient $yousign = new YousignClient(),
    ) {}

    public function name(): string
    {
        return 'yousign';
    }

    public function isConfigured(): bool
    {
        return $this->yousign->isConfigured();
    }

    public function requiresDocument(): bool
    {
        // Yousign téléverse le PDF de la convention à chaque demande.
        return true;
    }

    public function send(Investment $investment, string $pdfBinary, string $documentName, array $signers): array
    {
        // 1) Demande
        $req = $this->yousign->createSignatureRequest($documentName, 'investment-' . $investment->id);
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
        $this->yousign->addSigner($requestId, $this->signerPayload(
            $signers['investor']['name'], $signers['investor']['email'], $documentId, $fields['investor'], $fields,
        ));
        $this->yousign->addSigner($requestId, $this->signerPayload(
            $signers['owner']['name'], $signers['owner']['email'], $documentId, $fields['owner'], $fields,
        ));

        // 4) Activation (envoi des emails)
        $this->yousign->activate($requestId);

        return ['request_id' => (string) $requestId, 'sign_urls' => [], 'raw' => []];
    }

    public function status(Investment $investment): array
    {
        if (!$investment->signature_request_id) {
            return ['status' => 'unknown', 'raw' => 'none'];
        }

        $req = $this->yousign->getSignatureRequest($investment->signature_request_id);
        $raw = (string) ($req['status'] ?? 'unknown');

        return [
            'status' => match ($raw) {
                'done'     => 'completed',
                'declined' => 'declined',
                'expired'  => 'expired',
                'canceled' => 'declined',
                'ongoing'  => 'pending',
                default    => 'unknown',
            },
            'raw' => $raw,
        ];
    }

    public function downloadSigned(Investment $investment): ?string
    {
        if (!$investment->signature_request_id) {
            return null;
        }

        $req = $this->yousign->getSignatureRequest($investment->signature_request_id);
        $documentId = $req['documents'][0]['id'] ?? null;

        return $documentId
            ? $this->yousign->downloadDocument($investment->signature_request_id, $documentId)
            : null;
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
