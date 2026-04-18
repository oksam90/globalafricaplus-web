<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycSession extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'provider_session_id', 'redirect_url',
        'status', 'current_step',
        // Step 1
        'first_name', 'last_name', 'date_of_birth', 'place_of_birth',
        'nationality', 'gender', 'address', 'city', 'postal_code',
        'country', 'phone', 'person_type',
        'company_name', 'company_address', 'company_registration_number',
        'legal_form', 'legal_representative_name',
        // Step 2
        'document_type', 'document_number', 'document_expiry',
        'document_issuing_country', 'document_front_url', 'document_back_url',
        'selfie_url', 'proof_of_address_url', 'rccm_url', 'statuts_url',
        // Step 3
        'risk_level', 'source_of_funds', 'occupation',
        'expected_monthly_volume', 'is_pep', 'risk_factors',
        // Step 4
        'aml_checked', 'aml_clear', 'aml_results',
        'sanctions_clear', 'pep_clear',
        // Provider
        'provider_data', 'rejection_reason', 'notes',
        'verified_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'document_expiry' => 'date',
            'is_pep' => 'boolean',
            'aml_checked' => 'boolean',
            'aml_clear' => 'boolean',
            'sanctions_clear' => 'boolean',
            'pep_clear' => 'boolean',
            'risk_factors' => 'array',
            'aml_results' => 'array',
            'provider_data' => 'array',
            'expected_monthly_volume' => 'decimal:2',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'in_progress', 'documents_submitted']);
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if step 1 (identity) is complete.
     */
    public function step1Complete(): bool
    {
        if ($this->person_type === 'moral') {
            return $this->first_name && $this->last_name && $this->company_name && $this->company_registration_number;
        }
        return $this->first_name && $this->last_name && $this->date_of_birth && $this->nationality && $this->address;
    }

    /**
     * Check if step 2 (documents) is complete.
     */
    public function step2Complete(): bool
    {
        return $this->document_type && $this->document_number && $this->document_front_url;
    }

    /**
     * Check if step 3 (risk) is complete.
     */
    public function step3Complete(): bool
    {
        return $this->source_of_funds && $this->occupation;
    }

    /**
     * Check if step 4 (AML) is complete.
     */
    public function step4Complete(): bool
    {
        return $this->aml_checked;
    }

    /**
     * Compute overall completion percentage.
     */
    public function completionPercent(): int
    {
        $done = 0;
        if ($this->step1Complete()) $done++;
        if ($this->step2Complete()) $done++;
        if ($this->step3Complete()) $done++;
        if ($this->step4Complete()) $done++;
        return (int) round(($done / 4) * 100);
    }
}
