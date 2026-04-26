<?php

namespace App\Services\Payment;

use App\Services\Payment\DTOs\CheckoutResult;
use App\Services\Payment\DTOs\DisburseResult;
use App\Services\Payment\DTOs\PaymentStatus;
use App\Services\Payment\DTOs\RefundResult;

/**
 * Unified contract for all payment gateways integrated with Globalafrica+.
 *
 * Implementations: PayDunyaGateway, FlutterwaveGateway, StripeGateway, PayPalGateway.
 */
interface PaymentGatewayInterface
{
    /**
     * Create a hosted checkout / invoice URL for the user to complete payment.
     *
     * Expected $data keys:
     *  - amount (float)          required
     *  - currency (string)       required — ISO 4217
     *  - description (string)    required
     *  - reference (string)      required — internal transaction reference
     *  - customer (array)        required — [name, email, phone]
     *  - payment_type (string)   optional — subscription|investment|donation|training
     *  - channel (string)        optional — PayDunya channel key
     *  - custom_data (array)     optional — passed through to the webhook
     */
    public function createCheckout(array $data): CheckoutResult;

    /**
     * Verify and fetch the current status of a checkout/invoice by its token.
     */
    public function verifyPayment(string $token): PaymentStatus;

    /**
     * Refund a previously completed transaction.
     */
    public function refund(string $token, float $amount): RefundResult;

    /**
     * Disburse funds to a mobile money account (escrow release, payout).
     *
     * @param string $phone    Recipient phone in international format (e.g. +221XXXXXXXXX)
     * @param float  $amount
     * @param string $provider Mobile money provider key (orange-money-senegal, wave-senegal, ...)
     */
    public function disburse(string $phone, float $amount, string $provider): DisburseResult;

    /**
     * Return the current exchange rate between two currencies.
     * Used for multi-currency display (EUR/USD → XOF/XAF).
     */
    public function getExchangeRate(string $from, string $to): float;

    /**
     * Identifier of this gateway (e.g. "paydunya", "flutterwave", "stripe").
     */
    public function getName(): string;
}
