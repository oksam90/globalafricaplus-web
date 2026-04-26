<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InstallmentPlan extends Model
{
    protected $fillable = [
        'user_id', 'payable_type', 'payable_id', 'payment_type',
        'total_amount', 'currency',
        'total_installments', 'paid_installments',
        'frequency', 'status',
        'starts_at', 'next_due_at', 'completed_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'starts_at'    => 'datetime',
        'next_due_at'  => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('number');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopeDue(Builder $q): Builder
    {
        return $q->active()->where('next_due_at', '<=', now());
    }
}
