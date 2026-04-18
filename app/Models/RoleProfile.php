<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleProfile extends Model
{
    protected $fillable = [
        'user_id', 'role_id', 'data', 'completion', 'completed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Required fields per role for completion percentage.
     */
    public static function requiredFields(string $roleSlug): array
    {
        return match ($roleSlug) {
            'entrepreneur' => ['company_name', 'sectors', 'years_experience', 'pitch', 'legal_status'],
            'investor'     => ['investor_type', 'investment_min', 'investment_max', 'preferred_sectors'],
            'mentor'       => ['expertise', 'availability_hours_week', 'languages'],
            'jobseeker'    => ['headline', 'desired_roles', 'experience_years', 'availability', 'languages', 'education'],
            'government'   => ['ministry', 'position', 'official_email', 'country', 'mandate_scope'],
            'admin'        => ['department', 'responsibility', 'languages'],
            default        => [],
        };
    }

    public function recomputeCompletion(): void
    {
        $required = self::requiredFields($this->role->slug);
        if (empty($required)) {
            $this->completion = 100;
            $this->completed_at = now();
            return;
        }
        $filled = 0;
        foreach ($required as $field) {
            $value = $this->data[$field] ?? null;
            if (is_array($value) ? count($value) > 0 : !empty($value)) {
                $filled++;
            }
        }
        $this->completion = (int) round(($filled / count($required)) * 100);
        $this->completed_at = $this->completion === 100 ? now() : null;
    }
}
