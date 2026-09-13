<?php

namespace App\Services\Convention;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client de l'API DocuSeal (instance auto-hébergée).
 *
 * Authentification : en-tête `X-Auth-Token`.
 * Base : {DOCUSEAL_URL}/api  — ex. https://sign.globalafricaplus.com/api
 *
 * Docs : https://www.docuseal.com/docs/api
 */
class DocuSealClient
{
    private string $baseUrl;

    private string $token;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('docuseal.base_url'), '/') . '/api';
        $this->token   = (string) config('docuseal.api_token');
        $this->timeout = (int) config('docuseal.timeout', 30);
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->baseUrl !== '/api';
    }

    /**
     * POST /api/submissions/pdf — crée une demande de signature directement à
     * partir d'un PDF, sans gabarit permanent.
     */
    public function createSubmissionFromPdf(array $payload): array
    {
        return $this->handle($this->http()->post('/submissions/pdf', $payload), 'POST /submissions/pdf');
    }

    /**
     * POST /api/submissions — demande de signature depuis un GABARIT existant.
     * Seul mode disponible en édition libre auto-hébergée.
     */
    public function createSubmission(array $payload): array
    {
        return $this->handle($this->http()->post('/submissions', $payload), 'POST /submissions');
    }

    /** GET /api/templates — gabarits disponibles (id, nom, champs). */
    public function listTemplates(int $limit = 50): array
    {
        return $this->handle($this->http()->get('/templates', ['limit' => $limit]), 'GET /templates');
    }

    /** GET /api/submissions/{id} — statut de la demande. */
    public function getSubmission(int|string $id): array
    {
        return $this->handle($this->http()->get("/submissions/{$id}"), "GET /submissions/{$id}");
    }

    /**
     * GET /api/submissions/{id}/documents — documents de la demande.
     * Une fois la demande complétée, ce sont les PDF SIGNÉS qui sont renvoyés.
     */
    public function getSubmissionDocuments(int|string $id, bool $merge = true): array
    {
        return $this->handle(
            $this->http()->get("/submissions/{$id}/documents", ['merge' => $merge ? 'true' : 'false']),
            "GET /submissions/{$id}/documents",
        );
    }

    /** DELETE /api/submissions/{id} — archive la demande. */
    public function archiveSubmission(int|string $id): array
    {
        return $this->handle($this->http()->delete("/submissions/{$id}"), "DELETE /submissions/{$id}");
    }

    /**
     * Télécharge un document signé.
     *
     * L'URL renvoyée par l'API pointe vers l'instance DocuSeal et est déjà
     * signée : aucun en-tête d'authentification n'est nécessaire.
     */
    public function downloadFile(string $url): string
    {
        $resp = Http::timeout($this->timeout)->retry(2, 300, throw: false)->get($url);

        if ($resp->failed()) {
            throw new RuntimeException("DocuSeal : téléchargement du document signé impossible (HTTP {$resp->status()}).");
        }

        return $resp->body();
    }

    /** Sonde de configuration : liste un gabarit pour valider le jeton. */
    public function ping(): array
    {
        return $this->handle($this->http()->get('/templates', ['limit' => 1]), 'GET /templates');
    }

    // ─────────────────────────── transport ───────────────────────────

    private function http(): PendingRequest
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('DocuSeal non configuré (DOCUSEAL_API_TOKEN manquant).');
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-Auth-Token' => $this->token])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->retry(2, 300, throw: false);
    }

    private function handle(Response $response, string $context): array
    {
        if ($response->failed()) {
            $body = $response->json() ?? [];

            // L'édition libre auto-hébergée renvoie un 404 « Pro Edition » sur
            // les endpoints réservés (création de gabarit et soumission
            // « one-off » depuis un PDF/DOCX/HTML). Message explicite plutôt
            // qu'un 404 trompeur qui ferait chercher une URL erronée.
            if ($response->status() === 404 && str_contains((string) ($body['message'] ?? ''), 'Pro Edition')) {
                throw new RuntimeException(
                    "DocuSeal {$context} : cet endpoint est réservé à l'édition Pro. "
                    . "En édition libre, la convention doit passer par un gabarit créé depuis "
                    . "l'interface DocuSeal, puis POST /submissions avec son template_id."
                );
            }

            Log::warning('docuseal.http_error', [
                'context' => $context,
                'status'  => $response->status(),
                'error'   => $body['error'] ?? substr($response->body(), 0, 300),
            ]);

            throw new RuntimeException(
                "DocuSeal {$context} — HTTP {$response->status()} : "
                . ($body['error'] ?? substr($response->body(), 0, 200))
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
