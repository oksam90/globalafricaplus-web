<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\RoleProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleProfileController extends Controller
{
    /**
     * Schemas for role-specific profile validation.
     * Returns array [slug => rules].
     */
    protected function rulesFor(string $roleSlug): array
    {
        return match ($roleSlug) {
            'entrepreneur' => [
                'company_name' => ['nullable', 'string', 'max:150'],
                'sectors' => ['nullable', 'array'],
                'sectors.*' => ['string', 'max:60'],
                'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
                'website' => ['nullable', 'url', 'max:200'],
                'pitch' => ['nullable', 'string', 'max:2000'],
                'legal_status' => ['nullable', Rule::in(['informal', 'individual', 'suarl', 'sarl', 'sas', 'sa', 'gie', 'other'])],
                'registration_number' => ['nullable', 'string', 'max:100'],
                'tax_id' => ['nullable', 'string', 'max:100'],
                'registration_country' => ['nullable', 'string', 'max:80'],
                'founding_date' => ['nullable', 'date'],
                'team_size' => ['nullable', 'integer', 'min:1', 'max:10000'],
                'phone' => ['nullable', 'string', 'max:30'],
                'linkedin_url' => ['nullable', 'url', 'max:300'],
            ],
            'investor' => [
                'investor_type' => ['nullable', Rule::in(['individual', 'institutional', 'family_office', 'vc'])],
                'investment_min' => ['nullable', 'numeric', 'min:0'],
                'investment_max' => ['nullable', 'numeric', 'min:0'],
                'currency' => ['nullable', 'string', 'size:3'],
                'preferred_sectors' => ['nullable', 'array'],
                'preferred_sectors.*' => ['string', 'max:60'],
                'preferred_countries' => ['nullable', 'array'],
                'preferred_countries.*' => ['string', 'max:60'],
                'ticket_currency' => ['nullable', 'string', 'size:3'],
            ],
            'mentor' => [
                'expertise' => ['nullable', 'array'],
                'expertise.*' => ['string', 'max:60'],
                'hourly_rate' => ['nullable', 'numeric', 'min:0'],
                'currency' => ['nullable', 'string', 'size:3'],
                'availability_hours_week' => ['nullable', 'integer', 'min:0', 'max:60'],
                'languages' => ['nullable', 'array'],
                'languages.*' => ['string', 'max:20'],
                'linkedin_url' => ['nullable', 'url', 'max:200'],
            ],
            'jobseeker' => [
                'headline' => ['nullable', 'string', 'max:150'],
                'desired_roles' => ['nullable', 'array'],
                'desired_roles.*' => ['string', 'max:80'],
                'experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
                'availability' => ['nullable', Rule::in(['immediate', '1_month', '3_months'])],
                'cv_url' => ['nullable', 'url', 'max:300'],
                'open_to_remote' => ['nullable', 'boolean'],
                'education' => ['nullable', 'string', 'max:300'],
                'languages' => ['nullable', 'array'],
                'languages.*' => ['string', 'max:40'],
                'certifications' => ['nullable', 'array'],
                'certifications.*' => ['string', 'max:150'],
                'preferred_countries' => ['nullable', 'array'],
                'preferred_countries.*' => ['string', 'max:60'],
                'linkedin_url' => ['nullable', 'url', 'max:300'],
                'portfolio_url' => ['nullable', 'url', 'max:300'],
                'bio' => ['nullable', 'string', 'max:1000'],
            ],
            'government' => [
                'ministry' => ['nullable', 'string', 'max:150'],
                'position' => ['nullable', 'string', 'max:150'],
                'official_email' => ['nullable', 'email', 'max:150'],
                'country' => ['nullable', 'string', 'max:80'],
                'department' => ['nullable', 'string', 'max:150'],
                'mandate_scope' => ['nullable', 'string', 'max:300'],
                'priority_sectors' => ['nullable', 'array'],
                'priority_sectors.*' => ['string', 'max:60'],
                'website' => ['nullable', 'url', 'max:300'],
                'phone' => ['nullable', 'string', 'max:30'],
            ],
            'admin' => [
                'department' => ['nullable', 'string', 'max:150'],
                'responsibility' => ['nullable', 'string', 'max:300'],
                'phone_support' => ['nullable', 'string', 'max:30'],
                'moderation_notes' => ['nullable', 'string', 'max:2000'],
                'languages' => ['nullable', 'array'],
                'languages.*' => ['string', 'max:20'],
                'notification_channels' => ['nullable', 'array'],
                'notification_channels.*' => ['string', 'max:30'],
            ],
            default => [],
        };
    }

    public function show(Request $request, string $roleSlug): JsonResponse
    {
        $user = $request->user();
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        if (!$user->hasRole($roleSlug)) {
            return response()->json(['message' => "Vous ne possédez pas ce rôle."], 403);
        }

        $profile = RoleProfile::firstOrCreate(
            ['user_id' => $user->id, 'role_id' => $role->id],
            ['data' => [], 'completion' => 0]
        );

        return response()->json([
            'role' => $role,
            'profile' => $profile,
            'required_fields' => RoleProfile::requiredFields($roleSlug),
            'schema' => array_keys($this->rulesFor($roleSlug)),
        ]);
    }

    public function update(Request $request, string $roleSlug): JsonResponse
    {
        $user = $request->user();
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        if (!$user->hasRole($roleSlug)) {
            return response()->json(['message' => "Vous ne possédez pas ce rôle."], 403);
        }

        $rules = $this->rulesFor($roleSlug);
        $data = $request->validate($rules);

        $profile = RoleProfile::firstOrCreate(
            ['user_id' => $user->id, 'role_id' => $role->id],
            ['data' => [], 'completion' => 0]
        );

        $profile->data = array_merge($profile->data ?? [], $data);
        $profile->setRelation('role', $role);
        $profile->recomputeCompletion();
        $profile->save();

        return response()->json([
            'profile' => $profile,
        ]);
    }
}
