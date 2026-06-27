<?php

namespace App\Services\Convention;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Client minimal de l'API Yousign v3 (signature électronique).
 *
 * Cf. config/yousign.php pour le mode (sandbox/production), la clé API et le
 * niveau de signature.
 */
class YousignClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('yousign.base_url'), '/');
        $this->apiKey  = (string) config('yousign.api_key');
        $this->timeout = (int) config('yousign.timeout', 30);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->baseUrl !== '';
    }

    private function http(): PendingRequest
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('Yousign non configuré (YOUSIGN_API_KEY manquante).');
        }
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout);
    }

    /** 1) Crée une demande de signature (état "draft"). */
    public function createSignatureRequest(string $name, ?string $externalId = null): array
    {
        $resp = $this->http()->post('/signature_requests', array_filter([
            'name'          => $name,
            'delivery_mode' => 'email',
            'timezone'      => config('yousign.timezone', 'Africa/Dakar'),
            'external_id'   => $externalId,
        ]));
        return $this->ok($resp, 'createSignatureRequest');
    }

    /** 2) Téléverse le PDF à signer. */
    public function addDocument(string $requestId, string $pdfBinary, string $filename): array
    {
        $resp = $this->http()
            ->attach('file', $pdfBinary, $filename)
            ->post("/signature_requests/{$requestId}/documents", [
                'nature' => 'signable_document',
            ]);
        return $this->ok($resp, 'addDocument');
    }

    /** 3) Ajoute un signataire avec son champ de signature. */
    public function addSigner(string $requestId, array $payload): array
    {
        $resp = $this->http()->post("/signature_requests/{$requestId}/signers", $payload);
        return $this->ok($resp, 'addSigner');
    }

    /** 4) Active la demande → envoie les emails de signature. */
    public function activate(string $requestId): array
    {
        $resp = $this->http()->post("/signature_requests/{$requestId}/activate");
        return $this->ok($resp, 'activate');
    }

    /** Récupère l'état d'une demande (status, signers…). */
    public function getSignatureRequest(string $requestId): array
    {
        $resp = $this->http()->get("/signature_requests/{$requestId}");
        return $this->ok($resp, 'getSignatureRequest');
    }

    /** Télécharge le PDF (signé si la demande est terminée). Retourne les octets. */
    public function downloadDocument(string $requestId, string $documentId): string
    {
        $resp = $this->http()->get("/signature_requests/{$requestId}/documents/{$documentId}/download");
        if (!$resp->successful()) {
            throw new RuntimeException("Yousign downloadDocument: HTTP {$resp->status()}");
        }
        return $resp->body();
    }

    private function ok($resp, string $op): array
    {
        if (!$resp->successful()) {
            throw new RuntimeException("Yousign {$op}: HTTP {$resp->status()} — " . substr($resp->body(), 0, 300));
        }
        return $resp->json() ?? [];
    }
}
