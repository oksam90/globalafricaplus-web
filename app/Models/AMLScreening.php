<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 1 — one row per AML screening (Job Type 10).
 * Spec § 5.2.
 */
class AMLScreening extends Model
{
    use HasFactory;

    protected $table = 'aml_screenings';

    protected $fillable = [
        'user_id',
        'kyc_verification_id',
        'smile_job_id',
        'full_name_screened',
        'countries',
        'birth_year',
        'result_code',
        'sanctions_match',
        'pep_match',
        'adverse_media_match',
        'matches',
        'risk_level',
        'auto_reported',
        'screened_at',
    ];

    protected $casts = [
        'countries'           => 'array',
        'matches'             => 'array',
        'sanctions_match'     => 'boolean',
        'pep_match'           => 'boolean',
        'adverse_media_match' => 'boolean',
        'auto_reported'       => 'boolean',
        'screened_at'         => 'datetime',
    ];

    // Match details may include third-party PII — keep them out of JSON by default.
    protected $hidden = [
        'matches',
    ];

    // ── Relations ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kycVerification(): BelongsTo
    {
        return $this->belongsTo(KYCVerification::class, 'kyc_verification_id');
    }

    // ── Helpers ──

    public function hasAnyMatch(): bool
    {
        return $this->sanctions_match || $this->pep_match || $this->adverse_media_match;
    }

    public function isHighRisk(): bool
    {
        return in_array($this->risk_level, ['high', 'critical'], true);
    }

    /**
     * Derive a risk_level from the screening flags + an optional matches list.
     *
     * Scoring (spec § 5.2 + LCB-FT UEMOA Art. 18):
     *   - sanctions_match           → CRITICAL (auto-block + CENTIF report)
     *   - pep_match                 → HIGH     (manual review by compliance)
     *   - adverse_media (single)    → MEDIUM
     *   - adverse_media (3+ hits)   → HIGH     (sustained reputational risk)
     *   - sanctions + pep together  → CRITICAL (already covered, kept for clarity)
     *   - no flags                  → LOW
     *
     * The weighting is intentionally cautious — borderline cases escalate
     * upward so a human compliance officer reviews them.
     */
    public static function deriveRiskLevel(
        bool $sanctions,
        bool $pep,
        bool $adverseMedia,
        ?array $matches = null,
    ): string {
        if ($sanctions) {
            return 'critical';
        }
        if ($pep) {
            return 'high';
        }
        if ($adverseMedia) {
            // Escalate to HIGH when many adverse-media hits suggest sustained negative coverage.
            $adverseHits = is_array($matches)
                ? count(array_filter($matches, static fn ($m) => is_array($m)
                    && (($m['type'] ?? null) === 'adverse_media' || !empty($m['adverse_media']))))
                : 0;
            return $adverseHits >= 3 ? 'high' : 'medium';
        }
        return 'low';
    }

    // ── Scopes ──

    public function scopeFlagged($q)
    {
        return $q->where(function ($w) {
            $w->where('sanctions_match', true)
              ->orWhere('pep_match', true)
              ->orWhere('adverse_media_match', true);
        });
    }
}
