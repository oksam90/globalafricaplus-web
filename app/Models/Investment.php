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
        // Convention générée automatiquement (étape 4)
        'contract_type', 'contract_path', 'contract_status', 'contract_generated_at',
        // PDF + signature électronique (étapes 5 & 6)
        'contract_pdf_path', 'signature_provider', 'signature_request_id',
        'contract_signed_path', 'contract_sent_at', 'contract_signed_at',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'charged_amount'  => 'decimal:2',
        'paid_at'         => 'datetime',
        'refunded_at'     => 'datetime',
        'contract_generated_at' => 'datetime',
        'contract_sent_at'      => 'datetime',
        'contract_signed_at'    => 'datetime',
    ];

    // Chemins de stockage internes — exposés au front sous forme de booléens.
    protected $hidden = [
        'contract_path', 'contract_pdf_path', 'contract_signed_path', 'signature_request_id',
    ];

    protected $appends = ['has_contract', 'has_contract_pdf', 'has_signed_contract'];

    public function getHasContractAttribute(): bool
    {
        return !empty($this->contract_path);
    }

    public function getHasContractPdfAttribute(): bool
    {
        return !empty($this->contract_pdf_path);
    }

    public function getHasSignedContractAttribute(): bool
    {
        return !empty($this->contract_signed_path);
    }

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
