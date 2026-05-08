<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sprint 1 — one row per KYC submission to Smile Identity.
 * Spec § 5.1.
 */
class KYCVerification extends Model
{
    use HasFactory;

    protected $table = 'kyc_verifications';

    protected $fillable = [
        'user_id',
        'smile_job_id',
        'partner_job_id',
        'job_type',
        'country',
        'id_type',
        'id_number_hash',
        'result_code',
        'result_text',
        'confidence_value',
        'actions',
        'kyc_level_granted',
        'status',
        'callback_payload',
        'expires_at',
        'submitted_at',
        'completed_at',
    ];

    protected $casts = [
        'actions'          => 'array',
        'callback_payload' => 'array',
        'confidence_value' => 'decimal:2',
        'expires_at'       => 'datetime',
        'submitted_at'     => 'datetime',
        'completed_at'     => 'datetime',
    ];

    // Sensitive payloads should never leak in JSON responses.
    protected $hidden = [
        'callback_payload',
        'id_number_hash',
    ];

    // ── Relations ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amlScreenings(): HasMany
    {
        return $this->hasMany(AMLScreening::class, 'kyc_verification_id');
    }

    // ── Helpers ──

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    /**
     * Hash an identity number for safe storage. Salted with APP_KEY so a leak
     * of the DB alone cannot brute-force NINs / CNI numbers.
     */
    public static function hashIdNumber(string $idNumber): string
    {
        return hash_hmac('sha256', trim($idNumber), (string) config('app.key'));
    }

    // ── Scopes ──

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'approved')
                 ->where(function ($w) {
                     $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
                 });
    }
}
