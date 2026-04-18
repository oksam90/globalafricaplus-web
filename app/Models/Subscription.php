<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'billing_cycle', 'status',
        'starts_at', 'ends_at', 'trial_ends_at', 'cancelled_at',
        'payment_method', 'payment_reference',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    /**
     * Is this subscription currently valid (active or trialing)?
     */
    public function isValid(): bool
    {
        if ($this->status === 'active') {
            return !$this->ends_at || $this->ends_at->isFuture();
        }
        if ($this->status === 'trialing') {
            return !$this->trial_ends_at || $this->trial_ends_at->isFuture();
        }
        return false;
    }

    /**
     * Can user get a refund (within 30-day guarantee)?
     */
    public function isRefundable(): bool
    {
        return $this->starts_at && $this->starts_at->diffInDays(now()) <= 30;
    }
}
