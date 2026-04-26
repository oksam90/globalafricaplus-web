<?php

namespace App\Services\Payment\DTOs;

class RefundResult
{
    public function __construct(
        public readonly bool    $success,
        public readonly ?string $refundReference = null,
        public readonly ?float  $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $message = null,
        public readonly array   $raw = [],
    ) {}

    public static function success(string $refundReference, float $amount, string $currency, array $raw = []): self
    {
        return new self(true, $refundReference, $amount, $currency, null, $raw);
    }

    public static function failure(string $message, array $raw = []): self
    {
        return new self(false, null, null, null, $message, $raw);
    }
}
