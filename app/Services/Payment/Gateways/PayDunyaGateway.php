<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\CurrencyService;
use App\Services\Payment\DTOs\CheckoutResult;
use App\Services\Payment\DTOs\DisburseResult;
use App\Services\Payment\DTOs\PaymentStatus;
use App\Services\Payment\DTOs\RefundResult;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;
use Paydunya\Checkout\CheckoutInvoice;
use Paydunya\DirectPay;
use Throwable;

/**
 * PayDunya gateway adapter.
 *
 * Sprint 2 scope: createCheckout + verifyPayment (hosted invoice flow).
 * Sprint 4: disburse (escrow release).
 * Sprint 5: refund + multi-currency getExchangeRate.
 */
class PayDunyaGateway implements PaymentGatewayInterface
{
    protected array $config;

    public function __construct(
        protected ?CurrencyService $currency = null,
    ) {
        $this->config  = config('paydunya', []);
        $this->currency ??= app(CurrencyService::class);
    }

    public function getName(): string
    {
        return 'paydunya';
    }

    /**
     * Create a hosted PayDunya checkout invoice and return its URL + token.
     *
     * Required $data keys:
     *  - amount (float)
     *  - currency (string)
     *  - description (string)
     *  - reference (string)   — internal transaction id or uuid (goes into custom_data)
     *  - customer (array)     — [name, email, phone]
     * Optional:
     *  - channel (string)     — single PayDunya channel slug
     *  - channels (array)     — list of channel slugs to allow
     *  - payment_type (string) — subscription|investment|donation|training
     *  - custom_data (array)   — extra metadata passed through to the IPN
     *  - item_name (string)    — display name on the invoice
     *  - callback_url / return_url / cancel_url — override defaults
     */
    public function createCheckout(array $data): CheckoutResult
    {
        $required = ['amount', 'currency', 'description', 'reference', 'customer'];
        foreach ($required as $key) {
            if (!isset($data[$key])) {
                return CheckoutResult::failure("Missing required field: {$key}");
            }
        }

        try {
            $invoice = new CheckoutInvoice();

            $itemName = $data['item_name'] ?? $data['description'];
            $amount   = (float) $data['amount'];

            $invoice->addItem(
                $itemName,
                1,
                $amount,
                $amount,
                $data['description'],
            );

            $invoice->setTotalAmount($amount);
            $invoice->setDescription($data['description']);

            // Channels (optional — if omitted, PayDunya shows all store-enabled channels)
            if (!empty($data['channel'])) {
                $invoice->addChannel($data['channel']);
            } elseif (!empty($data['channels']) && is_array($data['channels'])) {
                foreach ($data['channels'] as $ch) {
                    $invoice->addChannel($ch);
                }
            }

            // URL overrides
            if (!empty($data['callback_url'])) $invoice->setCallbackUrl($data['callback_url']);
            if (!empty($data['return_url']))   $invoice->setReturnUrl($data['return_url']);
            if (!empty($data['cancel_url']))   $invoice->setCancelUrl($data['cancel_url']);

            // Custom data (echoed back in the IPN webhook)
            $invoice->addCustomData('reference', (string) $data['reference']);
            $invoice->addCustomData('payment_type', $data['payment_type'] ?? 'subscription');
            if (!empty($data['customer']['name']))  $invoice->addCustomData('customer_name',  $data['customer']['name']);
            if (!empty($data['customer']['email'])) $invoice->addCustomData('customer_email', $data['customer']['email']);
            if (!empty($data['customer']['phone'])) $invoice->addCustomData('customer_phone', $data['customer']['phone']);
            if (!empty($data['custom_data']) && is_array($data['custom_data'])) {
                foreach ($data['custom_data'] as $k => $v) {
                    $invoice->addCustomData($k, is_scalar($v) ? (string) $v : json_encode($v));
                }
            }

            if ($invoice->create()) {
                return CheckoutResult::success(
                    token: $invoice->token,
                    invoiceUrl: $invoice->getInvoiceUrl(),
                    qrCodeUrl: null,
                    raw: [
                        'response_code' => $invoice->response_code ?? null,
                        'response_text' => $invoice->response_text ?? null,
                    ],
                );
            }

            Log::warning('PayDunya checkout creation failed', [
                'code' => $invoice->response_code ?? null,
                'text' => $invoice->response_text ?? null,
                'reference' => $data['reference'],
            ]);

            return CheckoutResult::failure(
                $invoice->response_text ?? 'PayDunya checkout creation failed',
                [
                    'response_code' => $invoice->response_code ?? null,
                    'response_text' => $invoice->response_text ?? null,
                ],
            );
        } catch (Throwable $e) {
            Log::error('PayDunya createCheckout exception', [
                'message' => $e->getMessage(),
                'reference' => $data['reference'] ?? null,
            ]);
            return CheckoutResult::failure($e->getMessage());
        }
    }

    /**
     * Confirm invoice status directly with PayDunya (source of truth).
     */
    public function verifyPayment(string $token): PaymentStatus
    {
        try {
            $invoice = new CheckoutInvoice();
            $ok = $invoice->confirm($token);

            $status = $this->mapStatus($invoice->getStatus());

            // PayDunya customer data is only present once status is completed
            $customerName  = null;
            $customerEmail = null;
            $customerPhone = null;
            try { $customerName  = $invoice->getCustomerInfo('name');  } catch (Throwable $e) {}
            try { $customerEmail = $invoice->getCustomerInfo('email'); } catch (Throwable $e) {}
            try { $customerPhone = $invoice->getCustomerInfo('phone'); } catch (Throwable $e) {}

            return new PaymentStatus(
                status:        $status,
                token:         $token,
                amount:        (float) $invoice->getTotalAmount(),
                currency:      'XOF',
                customerName:  $customerName,
                customerEmail: $customerEmail,
                customerPhone: $customerPhone,
                paymentMethod: null,
                receiptUrl:    $invoice->getReceiptUrl(),
                customData:    (array) $invoice->showCustomData(),
                raw:           ['confirmed' => $ok, 'gateway_status' => $invoice->getStatus()],
            );
        } catch (Throwable $e) {
            Log::error('PayDunya verifyPayment exception', [
                'token' => $token,
                'message' => $e->getMessage(),
            ]);
            return new PaymentStatus(
                status: PaymentStatus::STATUS_FAILED,
                token: $token,
                raw: ['exception' => $e->getMessage()],
            );
        }
    }

    public function refund(string $token, float $amount): RefundResult
    {
        // TODO Sprint 5: PayDunya refunds are manual (dashboard / ticket).
        // The platform logs the intent and marks the transaction as refunded when the webhook arrives.
        return RefundResult::failure('PayDunya refunds require manual processing (Sprint 5).');
    }

    /**
     * Send funds to a mobile-money account via PayDunya DirectPay (credit-account).
     *
     * The PayDunya DirectPay endpoint uses the master account configured in the
     * PayDunya dashboard to debit, then credits the recipient phone. The provider
     * key is recorded in our logs but not passed to PayDunya — PayDunya routes
     * the credit based on the phone-number prefix.
     *
     * @param string $phone    Recipient phone in international format (e.g. +221771234567)
     * @param float  $amount   Amount in XOF (PayDunya disburse currency)
     * @param string $provider Mobile-money channel slug for our own audit (e.g. wave-senegal)
     */
    public function disburse(string $phone, float $amount, string $provider): DisburseResult
    {
        if (!class_exists(DirectPay::class)) {
            return DisburseResult::failure('PayDunya SDK not installed.');
        }

        $disburseConfig = $this->config['disburse'] ?? [];
        if (!($disburseConfig['enabled'] ?? true)) {
            return DisburseResult::failure('PayDunya disbursements are disabled by configuration.');
        }

        $min = (float) ($disburseConfig['min_amount_xof'] ?? 500);
        $max = (float) ($disburseConfig['max_amount_xof'] ?? 5_000_000);
        if ($amount < $min) {
            return DisburseResult::failure("Montant inférieur au minimum autorisé ({$min} XOF).");
        }
        if ($amount > $max) {
            return DisburseResult::failure("Montant supérieur au maximum autorisé ({$max} XOF).");
        }

        $normalizedPhone = $this->normalizePhone($phone);
        if (!$normalizedPhone) {
            return DisburseResult::failure('Numéro de téléphone destinataire invalide.');
        }

        try {
            $directPay = new DirectPay();
            $ok = $directPay->creditAccount($normalizedPhone, (int) round($amount));

            if ($ok) {
                return DisburseResult::success(
                    disburseReference: (string) ($directPay->transaction_id ?? ''),
                    amount:            $amount,
                    currency:          'XOF',
                    recipientPhone:    $normalizedPhone,
                    provider:          $provider,
                    raw: [
                        'response_code' => $directPay->response_code ?? null,
                        'response_text' => $directPay->response_text ?? null,
                        'description'   => $directPay->description ?? null,
                    ],
                );
            }

            Log::warning('PayDunya disburse rejected', [
                'phone'    => $normalizedPhone,
                'amount'   => $amount,
                'provider' => $provider,
                'code'     => $directPay->response_code ?? null,
                'text'     => $directPay->response_text ?? null,
            ]);

            return DisburseResult::failure(
                $directPay->response_text ?? 'PayDunya a rejeté le décaissement.',
                [
                    'response_code' => $directPay->response_code ?? null,
                    'response_text' => $directPay->response_text ?? null,
                ],
            );
        } catch (Throwable $e) {
            Log::error('PayDunya disburse exception', [
                'phone'   => $normalizedPhone,
                'amount'  => $amount,
                'message' => $e->getMessage(),
            ]);
            return DisburseResult::failure($e->getMessage());
        }
    }

    /**
     * Normalize a phone number to PayDunya's expected format.
     *
     * PayDunya accepts numbers in international form without the leading "+"
     * (e.g. 221771234567). Strip whitespace, dashes, parentheses and a leading
     * "+" if present, then validate the resulting string is digits only.
     */
    protected function normalizePhone(string $phone): ?string
    {
        $clean = preg_replace('/[\s\-()]/', '', trim($phone));
        $clean = ltrim((string) $clean, '+');
        if (!ctype_digit($clean) || strlen($clean) < 8) {
            return null;
        }
        return $clean;
    }

    public function getExchangeRate(string $from, string $to): float
    {
        return $this->currency->getRate($from, $to);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function isTestMode(): bool
    {
        return ($this->config['mode'] ?? 'test') === 'test';
    }

    /**
     * Map PayDunya status strings to our unified PaymentStatus constants.
     */
    protected function mapStatus(?string $gatewayStatus): string
    {
        return match (strtolower((string) $gatewayStatus)) {
            'completed' => PaymentStatus::STATUS_COMPLETED,
            'cancelled' => PaymentStatus::STATUS_CANCELLED,
            'failed'    => PaymentStatus::STATUS_FAILED,
            'refunded'  => PaymentStatus::STATUS_REFUNDED,
            default     => PaymentStatus::STATUS_PENDING,
        };
    }
}
