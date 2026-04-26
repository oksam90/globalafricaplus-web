<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'transactable_type', 'transactable_id',
        'amount', 'currency', 'platform_fee', 'gateway_fee', 'net_amount',
        'gateway', 'gateway_reference',
        'paydunya_token', 'paydunya_invoice_url', 'paydunya_receipt_url', 'paydunya_channel',
        'payment_type',
        'installment_number', 'installment_total',
        'status',
        'customer_name', 'customer_email', 'customer_phone', 'customer_country',
        'description', 'custom_data', 'ip_address', 'user_agent',
        'paid_at', 'refunded_at', 'failed_at',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'gateway_fee'  => 'decimal:2',
        'net_amount'   => 'decimal:2',
        'custom_data'  => 'array',
        'paid_at'      => 'datetime',
        'refunded_at'  => 'datetime',
        'failed_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function paymentLogs()
    {
        return $this->hasMany(PaymentLog::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }
}
