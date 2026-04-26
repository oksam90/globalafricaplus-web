<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Investment extends Model
{
    protected $fillable = [
        'project_id', 'investor_id',
        'amount', 'currency',
        'charged_amount', 'charged_currency',
        'type', 'status',
        'payment_provider', 'provider_reference',
        'transaction_id',
        'paydunya_token', 'paydunya_receipt_url', 'paydunya_channel',
        'paid_at', 'refunded_at',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'charged_amount'  => 'decimal:2',
        'paid_at'         => 'datetime',
        'refunded_at'     => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(EscrowMilestone::class)->orderBy('position');
    }

    // ---------- Scopes ----------

    public function scopePaid(Builder $q): Builder
    {
        return $q->whereIn('status', ['escrow', 'released']);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }
}
