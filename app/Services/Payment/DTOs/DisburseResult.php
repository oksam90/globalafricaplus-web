<?php

namespace App\Services\Payment\DTOs;

class DisburseResult
{
    public function __construct(
        public readonly bool    $success,
        public readonly ?string $disburseReference = null,
        public readonly ?float  $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $recipientPhone = null,
        public readonly ?string $provider = null,
        public readonly ?string $message = null,
        public readonly array   $raw = [],
    ) {}

    public static function success(
        string $disburseReference,
        float $amount,
        string $currency,
        string $recipientPhone,
        string $provider,
        array $raw = []
    ): self {
        return new self(true, $disburseReference, $amount, $currency, $recipientPhone, $provider, null, $raw);
    }

    public static function failure(string $message, array $raw = []): self
    {
        return new self(false, null, null, null, null, null, $message, $raw);
    }
}
