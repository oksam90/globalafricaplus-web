<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Investment extends Model
{
    protected $fillable = [
        'project_id', 'investor_id',
        'amount', 'currency',
        'charged_amount', 'charged_currency',
        // Ventilation « Montant Reçu » / frais / commission (PawaPay)
        'net_amount', 'platform_fee', 'platform_fee_rate', 'provider_fee',
        'fee_currency', 'fee_breakdown',
        'type', 'status',
        'payment_provider', 'provider_reference',
        'transaction_id',
        'paydunya_token', 'paydunya_receipt_url', 'paydunya_channel',
        'pawapay_deposit_id',
        'paid_at', 'refunded_at',
        // Convention générée automatiquement (étape 4)
        'contract_type', 'contract_path', 'contract_status', 'contract_generated_at',
        // PDF + signature électronique (étapes 5 & 6)
        'contract_pdf_path', 'signature_provider', 'signature_request_id', 'signature_sign_urls',
        'contract_signed_path', 'contract_sent_at', 'contract_signed_at',
    ];

    protected $casts = [
        'amount'          => 'decimal:2',
        'charged_amount'  => 'decimal:2',
        'net_amount'      => 'decimal:2',
        'platform_fee'    => 'decimal:2',
        'platform_fee_rate' => 'decimal:4',
        'provider_fee'    => 'decimal:2',
        'fee_breakdown'   => 'array',
        'signature_sign_urls' => 'array',
        'paid_at'         => 'datetime',
        'refunded_at'     => 'datetime',
        'contract_generated_at' => 'datetime',
        'contract_sent_at'      => 'datetime',
        'contract_signed_at'    => 'datetime',
    ];

    // Chemins de stockage internes — exposés au front sous forme de booléens.
    protected $hidden = [
        'contract_path', 'contract_pdf_path', 'contract_signed_path', 'signature_request_id',
        // Contient les liens de signature des DEUX parties : jamais exposé tel
        // quel, sinon une partie pourrait signer à la place de l'autre.
        'signature_sign_urls',
    ];

    protected $appends = ['has_contract', 'has_contract_pdf', 'has_signed_contract', 'my_sign_url'];

    /**
     * Lien de signature de l'utilisateur COURANT uniquement.
     *
     * DocuSeal renvoie un lien par signataire ; tant que le SMTP de l'instance
     * n'est pas configuré, aucun email n'est envoyé et ce lien est le seul
     * moyen de signer. Chacun ne voit que le sien.
     */
    public function getMySignUrlAttribute(): ?string
    {
        $urls = $this->signature_sign_urls;
        $userId = auth()->id();

        if (!is_array($urls) || $urls === [] || !$userId || $this->contract_status === 'signed') {
            return null;
        }

        // Les liens sont indexés sur une clé interne stable : le libellé du
        // rôle change d'un type de convention à l'autre (Prêteur/Emprunteur,
        // Donateur/Bénéficiaire…) et ne peut pas servir d'index ici.
        if ((int) $this->investor_id === (int) $userId) {
            return $urls['investor'] ?? null;
        }

        // Le porteur de projet : on évite de charger la relation si elle ne
        // l'est pas déjà (cet accesseur est appelé à chaque sérialisation).
        $ownerId = $this->relationLoaded('project')
            ? $this->project?->user_id
            : Project::whereKey($this->project_id)->value('user_id');

        return (int) $ownerId === (int) $userId ? ($urls['owner'] ?? null) : null;
    }

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

    /**
     * Plan de paiement fractionné éventuel (2 à 12 échéances).
     *
     * Relation polymorphe inverse : InstallmentPlan porte `payable_type` /
     * `payable_id`. Utilisée par la convention, qui doit décrire l'échéancier
     * réellement souscrit et non un versement unique fictif.
     */
    public function installmentPlan(): MorphOne
    {
        return $this->morphOne(InstallmentPlan::class, 'payable')->latestOfMany();
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
