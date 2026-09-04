<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\CurrencyService;
use App\Services\Payment\DTOs\CheckoutResult;
use App\Services\Payment\DTOs\DisburseResult;
use App\Services\Payment\DTOs\PaymentStatus;
use App\Services\Payment\DTOs\RefundResult;
use App\Services\Payment\FeeCalculator;
use App\Services\Payment\PawaPayClient;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adaptateur PawaPay (Merchant API v2) — PSP mobile money par défaut depuis
 * septembre 2026, en remplacement de PayDunya (dont la configuration reste en
 * place et réactivable via PAYMENT_GATEWAY=paydunya).
 *
 * Correspondance avec PaymentGatewayInterface :
 *   createCheckout()  → POST /v2/paymentpage  (checkout hébergé, redirectUrl)
 *   verifyPayment()   → GET  /v2/deposits/{depositId}
 *   disburse()        → POST /v2/payouts
 *   refund()          → POST /v2/refunds
 *
 * Le « token » manipulé par le reste de l'application est le **depositId**
 * (UUID v4 que NOUS générons avant l'appel, ce qui rend l'opération
 * idempotente et réconciliable même en cas de coupure réseau).
 */
class PawaPayGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected PawaPayClient $client,
        protected FeeCalculator $fees,
        protected CurrencyService $currency,
    ) {}

    public function getName(): string
    {
        return 'pawapay';
    }

    public function isTestMode(): bool
    {
        return config('pawapay.mode', 'sandbox') !== 'production';
    }

    /**
     * Ouvre une Payment Page PawaPay et renvoie son URL de redirection.
     *
     * Clés attendues dans $data :
     *  - amount (float)      : MONTANT ENVOYÉ (déjà majoré des frais + commission)
     *  - currency (string)   : devise du marché (XAF, XOF, KES…)
     *  - description (string)
     *  - reference (string)  : id de transaction interne
     *  - customer (array)    : [name, email, phone]
     * Optionnel :
     *  - country (string)    : ISO-2 du marché (défaut : déduit de la devise)
     *  - provider (string)   : code opérateur PawaPay pour pré-sélectionner
     *  - deposit_id (string) : UUID imposé (réutilisation / reprise)
     *  - return_url (string)
     *  - custom_data (array) : métadonnées renvoyées dans le callback
     */
    public function createCheckout(array $data): CheckoutResult
    {
        foreach (['amount', 'currency', 'description', 'reference', 'customer'] as $key) {
            if (!isset($data[$key])) {
                return CheckoutResult::failure("Missing required field: {$key}");
            }
        }

        $depositId = (string) ($data['deposit_id'] ?? Str::uuid());
        $countryIso2 = strtoupper((string) ($data['country'] ?? $this->fees->normalizeCountry(null)));
        $market      = $this->fees->market($countryIso2);
        $decimals    = (int) ($market['decimals'] ?? 0);

        $returnUrl = (string) ($data['return_url'] ?? config('pawapay.return_url'));
        $returnUrl .= (str_contains($returnUrl, '?') ? '&' : '?')
            . http_build_query(['provider' => 'pawapay', 'deposit_id' => $depositId]);

        $payload = [
            'depositId'       => $depositId,
            'returnUrl'       => $returnUrl,
            'amountDetails'   => [
                'amount'   => $this->formatAmount((float) $data['amount'], $decimals),
                'currency' => strtoupper((string) $data['currency']),
            ],
            'country'         => (string) ($market['iso3'] ?? 'SEN'),
            'language'        => strtoupper((string) config('pawapay.payment_page.language', 'FR')),
            'reason'          => $this->truncate((string) $data['description'], 50),
            'customerMessage' => $this->customerMessage(),
            'metadata'        => $this->metadata($data),
        ];

        // Pré-remplissage du numéro si on le connaît (accélère le tunnel).
        if ($phone = $this->normalizePhone((string) ($data['customer']['phone'] ?? ''), $market)) {
            $payload['phoneNumber'] = $phone;
        }

        try {
            $response = $this->client->createPaymentPage($payload);
        } catch (Throwable $e) {
            Log::error('pawapay.payment_page_failed', [
                'reference' => $data['reference'] ?? null,
                'message'   => $e->getMessage(),
            ]);
            return CheckoutResult::failure($e->getMessage());
        }

        $redirect = $response['redirectUrl'] ?? null;

        if (!$redirect) {
            $message = data_get($response, 'failureReason.failureMessage')
                ?? 'PawaPay n\'a pas renvoyé d\'URL de paiement.';

            Log::warning('pawapay.payment_page_rejected', [
                'reference' => $data['reference'] ?? null,
                'status'    => $response['status'] ?? null,
                'code'      => data_get($response, 'failureReason.failureCode'),
            ]);

            return CheckoutResult::failure($message, $response);
        }

        return CheckoutResult::success(
            token: $depositId,
            invoiceUrl: $redirect,
            qrCodeUrl: null,
            raw: $response,
        );
    }

    /**
     * Statut d'un dépôt. `$token` = depositId.
     */
    public function verifyPayment(string $token): PaymentStatus
    {
        try {
            $response = $this->client->getDeposit($token);
        } catch (Throwable $e) {
            Log::error('pawapay.verify_failed', ['deposit_id' => $token, 'message' => $e->getMessage()]);
            return new PaymentStatus(
                status: PaymentStatus::STATUS_PENDING,
                token: $token,
                raw: ['exception' => $e->getMessage()],
            );
        }

        // v2 : { "status": "FOUND"|"NOT_FOUND", "data": { … } }
        // On accepte aussi un objet dépôt « nu » (payload de callback).
        $deposit = $response['data'] ?? $response;

        if (($response['status'] ?? null) === 'NOT_FOUND' || empty($deposit['depositId'])) {
            return new PaymentStatus(
                status: PaymentStatus::STATUS_PENDING,
                token: $token,
                raw: $response,
            );
        }

        $metadata = (array) ($deposit['metadata'] ?? []);

        return new PaymentStatus(
            status:        $this->mapStatus($deposit['status'] ?? null),
            token:         (string) $deposit['depositId'],
            amount:        isset($deposit['amount']) ? (float) $deposit['amount'] : null,
            currency:      $deposit['currency'] ?? null,
            customerName:  null,
            customerEmail: $metadata['customer_email'] ?? null,
            customerPhone: data_get($deposit, 'payer.accountDetails.phoneNumber'),
            paymentMethod: data_get($deposit, 'payer.accountDetails.provider'),
            receiptUrl:    null,
            customData:    $metadata,
            raw:           $response,
        );
    }

    /**
     * Remboursement d'un dépôt encaissé. `$token` = depositId d'origine.
     *
     * Rappel des règles de prise en charge des frais (config/payments.php) :
     *  - non-décaissement par le porteur → frais à la charge de la plateforme
     *  - litige après perception          → frais à la charge du porteur
     * Le PSP prélève de toute façon ses frais côté entreprise ; la ventilation
     * comptable est portée par RefundService.
     */
    public function refund(string $token, float $amount): RefundResult
    {
        $refundId = (string) Str::uuid();

        try {
            $deposit = $this->client->getDeposit($token)['data'] ?? [];
            $currency = $deposit['currency'] ?? null;
            $decimals = str_contains((string) ($deposit['amount'] ?? ''), '.') ? 2 : 0;

            $payload = array_filter([
                'refundId'          => $refundId,
                'depositId'         => $token,
                'amount'            => $amount > 0 ? $this->formatAmount($amount, $decimals) : null,
                'currency'          => $currency,
                'clientReferenceId' => 'refund-' . substr($token, 0, 8),
            ]);

            $response = $this->client->createRefund($payload);
            $status   = $response['status'] ?? 'REJECTED';

            if (in_array($status, ['ACCEPTED', 'DUPLICATE_IGNORED'], true)) {
                return RefundResult::success(
                    refundReference: $refundId,
                    amount: $amount,
                    currency: (string) ($currency ?? ''),
                    raw: $response,
                );
            }

            return RefundResult::failure(
                data_get($response, 'failureReason.failureMessage') ?? 'Remboursement refusé par PawaPay.',
                $response,
            );
        } catch (Throwable $e) {
            Log::error('pawapay.refund_failed', ['deposit_id' => $token, 'message' => $e->getMessage()]);
            return RefundResult::failure($e->getMessage());
        }
    }

    /**
     * Décaissement vers le mobile money du porteur de projet (libération
     * d'un jalon d'escrow).
     *
     * @param string $phone    Numéro international (avec ou sans « + »)
     * @param float  $amount   Montant NET que le porteur doit recevoir
     * @param string $provider Code opérateur PawaPay (ex. AIRTEL_GAB) ou ISO-2
     *                         du pays (l'opérateur par défaut est alors retenu)
     */
    public function disburse(string $phone, float $amount, string $provider): DisburseResult
    {
        if (!config('pawapay.disburse.enabled', true)) {
            return DisburseResult::failure('Les décaissements PawaPay sont désactivés par configuration.');
        }

        [$providerCode, $market] = $this->resolvePayoutProvider($provider);
        $decimals = (int) ($market['decimals'] ?? 0);
        $currency = (string) $market['currency'];

        $msisdn = $this->normalizePhone($phone, $market);
        if (!$msisdn) {
            return DisburseResult::failure('Numéro de téléphone destinataire invalide.');
        }

        $payoutId = (string) Str::uuid();

        try {
            $response = $this->client->createPayout([
                'payoutId'  => $payoutId,
                'recipient' => [
                    'type'           => 'MMO',
                    'accountDetails' => [
                        'phoneNumber' => $msisdn,
                        'provider'    => $providerCode,
                    ],
                ],
                'amount'          => $this->formatAmount($amount, $decimals),
                'currency'        => $currency,
                'customerMessage' => $this->customerMessage(),
            ]);

            $status = $response['status'] ?? 'REJECTED';

            if (in_array($status, ['ACCEPTED', 'DUPLICATE_IGNORED'], true)) {
                return DisburseResult::success(
                    disburseReference: $payoutId,
                    amount:            $amount,
                    currency:          $currency,
                    recipientPhone:    $msisdn,
                    provider:          $providerCode,
                    raw:               $response,
                );
            }

            Log::warning('pawapay.payout_rejected', [
                'payout_id' => $payoutId,
                'provider'  => $providerCode,
                'code'      => data_get($response, 'failureReason.failureCode'),
            ]);

            return DisburseResult::failure(
                data_get($response, 'failureReason.failureMessage') ?? 'PawaPay a rejeté le décaissement.',
                $response,
            );
        } catch (Throwable $e) {
            Log::error('pawapay.payout_failed', [
                'payout_id' => $payoutId,
                'message'   => $e->getMessage(),
            ]);
            return DisburseResult::failure($e->getMessage());
        }
    }

    public function getExchangeRate(string $from, string $to): float
    {
        return $this->currency->getRate($from, $to);
    }

    /** Configuration réellement active sur le compte marchand (Toolkit). */
    public function activeConfiguration(): array
    {
        return $this->client->activeConfiguration();
    }

    // ─────────────────────────── helpers ───────────────────────────

    /**
     * Mappe les statuts PawaPay vers les constantes internes.
     * ACCEPTED / PROCESSING / IN_RECONCILIATION → pending (non final)
     * COMPLETED → completed ; FAILED / REJECTED → failed.
     */
    protected function mapStatus(?string $gatewayStatus): string
    {
        return match (strtoupper((string) $gatewayStatus)) {
            'COMPLETED'          => PaymentStatus::STATUS_COMPLETED,
            'FAILED', 'REJECTED' => PaymentStatus::STATUS_FAILED,
            'CANCELLED'          => PaymentStatus::STATUS_CANCELLED,
            'REFUNDED'           => PaymentStatus::STATUS_REFUNDED,
            default              => PaymentStatus::STATUS_PENDING,
        };
    }

    /**
     * @return array{0:string,1:array} [providerCode, market]
     */
    protected function resolvePayoutProvider(string $provider): array
    {
        $markets = config('pawapay.markets', []);

        // Cas 1 : code opérateur PawaPay explicite (MTN_MOMO_CMR…)
        foreach ($markets as $market) {
            if (isset($market['providers'][$provider])) {
                return [$provider, $market];
            }
        }

        // Cas 2 : code pays (ISO-2 / ISO-3 / nom) → opérateur par défaut
        $iso2   = $this->fees->normalizeCountry($provider);
        $market = $this->fees->market($iso2);
        [$code] = $this->fees->resolveProvider($market, null);

        return [$code, $market];
    }

    /**
     * MSISDN attendu par PawaPay : chiffres uniquement, indicatif pays inclus,
     * sans « + » ni « 00 » ni zéro national de tête.
     */
    protected function normalizePhone(string $phone, array $market): ?string
    {
        $clean = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';
        $clean = ltrim($clean, '+');
        $clean = preg_replace('/^00/', '', $clean) ?? '';

        if ($clean === '') {
            return null;
        }

        $prefix = (string) ($market['prefix'] ?? '');

        // Numéro national (ex. 077123456 au Gabon) → on préfixe l'indicatif.
        if ($prefix !== '' && !str_starts_with($clean, $prefix)) {
            $clean = $prefix . ltrim($clean, '0');
        }

        return strlen($clean) >= 8 && ctype_digit($clean) ? $clean : null;
    }

    /**
     * PawaPay impose 4 à 22 caractères alphanumériques + espaces pour le
     * libellé affiché au client.
     */
    protected function customerMessage(): string
    {
        $raw = (string) config('pawapay.payment_page.statement_description', 'GlobalAfrica Plus');
        $clean = trim(preg_replace('/[^A-Za-z0-9 ]/', ' ', $raw) ?? '');
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');
        $clean = substr($clean, 0, 22);

        return strlen($clean) >= 4 ? $clean : 'GlobalAfrica Plus';
    }

    /**
     * Métadonnées PawaPay : tableau d'objets clé/valeur (10 max), avec un
     * marqueur `isPII` pour les données personnelles.
     */
    protected function metadata(array $data): array
    {
        $custom = (array) ($data['custom_data'] ?? []);
        $custom['reference'] = (string) $data['reference'];

        $meta = [];
        foreach (array_slice($custom, 0, 9, true) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $meta[] = [(string) $key => is_scalar($value) ? (string) $value : json_encode($value)];
        }

        if (!empty($data['customer']['email'])) {
            $meta[] = ['customer_email' => (string) $data['customer']['email'], 'isPII' => true];
        }

        return array_slice($meta, 0, 10);
    }

    protected function formatAmount(float $amount, int $decimals): string
    {
        return number_format($amount, $decimals, '.', '');
    }

    protected function truncate(string $value, int $max): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        return mb_substr($value, 0, $max);
    }
}
