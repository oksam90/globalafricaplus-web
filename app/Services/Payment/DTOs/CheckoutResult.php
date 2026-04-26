<?php

namespace App\Services\Payment\DTOs;

/**
 * Result returned by PaymentGatewayInterface::createCheckout().
 */
class CheckoutResult
{
    public function __construct(
        public readonly bool    $success,
        public readonly ?string $token = null,
        public readonly ?string $invoiceUrl = null,
        public readonly ?string $qrCodeUrl = null,
        public readonly ?string $message = null,
        public readonly array   $raw = [],
    ) {}

    public static function success(string $token, string $invoiceUrl, ?string $qrCodeUrl = null, array $raw = []): self
    {
        return new self(true, $token, $invoiceUrl, $qrCodeUrl, null, $raw);
    }

    public static function failure(string $message, array $raw = []): self
    {
        return new self(false, null, null, null, $message, $raw);
    }
}
