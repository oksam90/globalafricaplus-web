<?php

namespace App\Services\Payment;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client HTTP bas niveau pour la Merchant API PawaPay v2.
 *
 * Authentification : `Authorization: Bearer <API token>` (token généré depuis
 * le Dashboard, distinct entre sandbox et production).
 *
 * Base URL :
 *   sandbox    → https://api.sandbox.pawapay.io
 *   production → https://api.pawapay.io
 *
 * Toutes les opérations d'écriture sont IDEMPOTENTES côté PawaPay grâce à
 * l'identifiant UUID fourni par nos soins (depositId / payoutId / refundId) :
 * rejouer la même requête renvoie `DUPLICATE_IGNORED` au lieu de dupliquer.
 */
class PawaPayClient
{
    public function __construct(
        protected ?string $token = null,
        protected ?string $baseUrl = null,
    ) {
        $this->token   ??= (string) config('pawapay.api_token');
        $this->baseUrl ??= rtrim((string) config('pawapay.base_url'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->baseUrl !== '';
    }

    /** POST /v2/deposits — demande de paiement directe (push USSD/PIN). */
    public function createDeposit(array $payload): array
    {
        return $this->post('/v2/deposits', $payload);
    }

    /** POST /v2/paymentpage — checkout hébergé, renvoie `redirectUrl`. */
    public function createPaymentPage(array $payload): array
    {
        return $this->post('/v2/paymentpage', $payload);
    }

    /** GET /v2/deposits/{depositId} — statut (source de vérité). */
    public function getDeposit(string $depositId): array
    {
        return $this->get("/v2/deposits/{$depositId}");
    }

    /** POST /v2/payouts — décaissement vers un wallet mobile money. */
    public function createPayout(array $payload): array
    {
        return $this->post('/v2/payouts', $payload);
    }

    /** GET /v2/payouts/{payoutId} */
    public function getPayout(string $payoutId): array
    {
        return $this->get("/v2/payouts/{$payoutId}");
    }

    /** POST /v2/refunds — remboursement d'un dépôt encaissé. */
    public function createRefund(array $payload): array
    {
        return $this->post('/v2/refunds', $payload);
    }

    /** GET /v2/refunds/{refundId} */
    public function getRefund(string $refundId): array
    {
        return $this->get("/v2/refunds/{$refundId}");
    }

    /**
     * GET /v2/active-conf — pays, opérateurs, devises, bornes min/max et état
     * opérationnel réellement activés sur le compte marchand.
     */
    public function activeConfiguration(): array
    {
        return $this->get('/v2/active-conf');
    }

    // ─────────────────────────── transport ───────────────────────────

    protected function http(): PendingRequest
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('PawaPay n\'est pas configuré (PAWAPAY_API_TOKEN manquant).');
        }

        return Http::withToken($this->token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('pawapay.timeout', 20))
            ->retry(2, 300, throw: false)
            ->baseUrl($this->baseUrl);
    }

    protected function get(string $path): array
    {
        return $this->handle($this->http()->get($path), 'GET ' . $path);
    }

    protected function post(string $path, array $payload): array
    {
        return $this->handle($this->http()->post($path, $payload), 'POST ' . $path);
    }

    /**
     * PawaPay répond 200 avec un `status` métier (ACCEPTED / REJECTED /
     * DUPLICATE_IGNORED). On ne lève donc que sur les erreurs de transport ;
     * le statut métier est laissé à l'appelant.
     */
    protected function handle(Response $response, string $context): array
    {
        $body = $response->json() ?? [];

        if ($response->failed()) {
            Log::warning('pawapay.http_error', [
                'context' => $context,
                'status'  => $response->status(),
                'body'    => $this->redact($body),
            ]);

            throw new RuntimeException(
                'PawaPay ' . $context . ' — HTTP ' . $response->status() . ' : '
                . (data_get($body, 'failureReason.failureMessage')
                    ?? data_get($body, 'message')
                    ?? $response->body())
            );
        }

        return is_array($body) ? $body : [];
    }

    /** Retire les données personnelles des logs (numéro du payeur). */
    protected function redact(array $body): array
    {
        foreach (['payer.accountDetails.phoneNumber', 'recipient.accountDetails.phoneNumber', 'data.payer.accountDetails.phoneNumber'] as $path) {
            if (data_get($body, $path)) {
                data_set($body, $path, '***');
            }
        }

        return $body;
    }
}
