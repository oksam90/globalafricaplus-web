<?php

namespace App\Services\Payment\DTOs;

/**
 * Unified payment status returned by PaymentGatewayInterface::verifyPayment().
 */
class PaymentStatus
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED  = 'refunded';

    public function __construct(
        public readonly string  $status,        // one of the STATUS_* constants
        public readonly ?string $token = null,
        public readonly ?float  $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $customerName = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerPhone = null,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $receiptUrl = null,
        public readonly array   $customData = [],
        public readonly array   $raw = [],
    ) {}

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
