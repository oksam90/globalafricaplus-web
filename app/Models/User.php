<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'password',
        'country', 'city', 'avatar', 'bio',
        'kyc_level', 'is_diaspora', 'residence_country', 'preferred_language',
        'active_role_slug',
        // Smile Identity (Sprint 1+2)
        'kyc_verified_at', 'kyc_expires_at', 'kyc_verification_id',
        'aml_status', 'aml_last_checked_at', 'selfie_registered',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['active_role'];

    protected function casts(): array
    {
        return [
            'email_verified_at'    => 'datetime',
            'password'             => 'hashed',
            'is_diaspora'          => 'boolean',
            // Smile Identity
            'kyc_verified_at'     => 'datetime',
            'kyc_expires_at'      => 'datetime',
            'aml_last_checked_at' => 'datetime',
            'selfie_registered'   => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function roleProfiles(): HasMany
    {
        return $this->hasMany(RoleProfile::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class, 'investor_id');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)
            ->withPivot('level', 'years_experience')
            ->withTimestamps();
    }

    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * True once the user has reached the `verified` Smile tier or above and is
     * within the 24-month validity window. Replaces the legacy IDnorm helper.
     */
    public function isKycVerified(): bool
    {
        if (!in_array($this->kyc_level, ['verified', 'certified'], true)) {
            return false;
        }
        return $this->kyc_expires_at === null || $this->kyc_expires_at->isFuture();
    }

    /**
     * True while the most recent Smile submission is still mid-flight (pending
     * or processing) — used by the dashboard to show "vérification en cours".
     */
    public function hasKycPending(): bool
    {
        return $this->kycVerifications()
            ->whereIn('status', ['pending', 'processing'])
            ->exists();
    }

    // ── Smile Identity (Sprint 1+2) ──

    public function kycVerifications(): HasMany
    {
        return $this->hasMany(KYCVerification::class);
    }

    public function amlScreenings(): HasMany
    {
        return $this->hasMany(AMLScreening::class);
    }

    /** Most recent Smile-driven KYC verification (any status). */
    public function latestSmileVerification(): ?KYCVerification
    {
        return $this->kycVerifications()->latest('submitted_at')->first();
    }

    /** Most recent AML screening, regardless of risk_level. */
    public function latestAmlScreening(): ?AMLScreening
    {
        return $this->amlScreenings()->latest('screened_at')->first();
    }

    /**
     * Get the current active subscription (active or trialing).
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->with('plan')
            ->latest('starts_at')
            ->first();
    }

    /**
     * Check if user has a valid (non-free) subscription.
     */
    public function hasActiveSubscription(): bool
    {
        $sub = $this->activeSubscription();
        return $sub && $sub->isValid() && !$sub->plan->isFree();
    }

    /**
     * Get the current plan slug (or 'free' if none).
     */
    public function currentPlanSlug(): string
    {
        $sub = $this->activeSubscription();
        return ($sub && $sub->isValid()) ? $sub->plan->slug : 'free';
    }

    public function mentorshipsAsMentor(): HasMany
    {
        return $this->hasMany(Mentorship::class, 'mentor_id');
    }

    public function mentorshipsAsMentee(): HasMany
    {
        return $this->hasMany(Mentorship::class, 'mentee_id');
    }

    public function mentorAvailabilities(): HasMany
    {
        return $this->hasMany(MentorAvailability::class);
    }

    public function mentorReviewsReceived(): HasMany
    {
        return $this->hasMany(MentorReview::class, 'reviewed_id');
    }

    /**
     * Average mentor rating from received reviews.
     */
    public function averageMentorRating(): float
    {
        return (float) round($this->mentorReviewsReceived()->avg('rating') ?? 0, 1);
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    public function hasAnyRole(array $slugs): bool
    {
        return $this->roles()->whereIn('slug', $slugs)->exists();
    }

    /**
     * Attach a role and create an empty role-profile shell.
     */
    public function assignRole(string $slug): Role
    {
        $role = Role::where('slug', $slug)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$role->id]);

        RoleProfile::firstOrCreate(
            ['user_id' => $this->id, 'role_id' => $role->id],
            ['data' => [], 'completion' => 0]
        );

        // First role becomes the active one by default
        if (empty($this->active_role_slug)) {
            $this->forceFill(['active_role_slug' => $slug])->save();
        }

        return $role;
    }

    public function removeRole(string $slug): void
    {
        $role = Role::where('slug', $slug)->first();
        if (!$role) return;

        $this->roles()->detach($role->id);
        RoleProfile::where('user_id', $this->id)->where('role_id', $role->id)->delete();

        if ($this->active_role_slug === $slug) {
            $fallback = $this->roles()->value('slug');
            $this->forceFill(['active_role_slug' => $fallback])->save();
        }
    }

    public function getActiveRoleAttribute(): ?string
    {
        return $this->active_role_slug;
    }

    /**
     * Get the role profile for a given role slug.
     */
    public function profileFor(string $roleSlug): ?RoleProfile
    {
        $role = Role::where('slug', $roleSlug)->first();
        if (!$role) return null;
        return $this->roleProfiles()->where('role_id', $role->id)->first();
    }
}
