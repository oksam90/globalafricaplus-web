<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingPurchase extends Model
{
    protected $fillable = [
        'user_id', 'training_id', 'transaction_id',
        'amount', 'currency', 'status',
        'paid_at', 'refund_deadline', 'refunded_at', 'access_revoked_at',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'paid_at'           => 'datetime',
        'refund_deadline'   => 'datetime',
        'refunded_at'       => 'datetime',
        'access_revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function training(): BelongsTo
    {
        return $this->belongsTo(Training::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->access_revoked_at;
    }

    /**
     * Within the 30-day money-back guarantee window?
     */
    public function isRefundable(): bool
    {
        if ($this->status !== 'active' || !$this->refund_deadline) return false;
        return $this->refund_deadline->isFuture();
    }
}
