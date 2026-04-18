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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['active_role'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_diaspora' => 'boolean',
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

    public function kycSessions(): HasMany
    {
        return $this->hasMany(KycSession::class);
    }

    /**
     * Get the latest KYC session.
     */
    public function latestKycSession(): ?KycSession
    {
        return $this->kycSessions()->latest()->first();
    }

    /**
     * Check if user has a verified KYC.
     */
    public function isKycVerified(): bool
    {
        return $this->kyc_level === 'verified' || $this->kyc_level === 'certified';
    }

    /**
     * Check if user has a pending KYC session.
     */
    public function hasKycPending(): bool
    {
        return $this->kycSessions()
            ->whereIn('status', ['pending', 'in_progress', 'documents_submitted'])
            ->exists();
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
