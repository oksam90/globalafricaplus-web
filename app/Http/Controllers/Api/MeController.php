<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MeController extends Controller
{
    /**
     * Current user with roles + role-profiles.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles', 'roleProfiles.role']);
        return response()->json(['user' => $user]);
    }

    /**
     * Update basic profile fields.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'country' => ['sometimes', 'nullable', 'string', 'max:120'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_diaspora' => ['sometimes', 'boolean'],
            'residence_country' => ['sometimes', 'nullable', 'string', 'max:120'],
            'preferred_language' => ['sometimes', 'string', 'size:2'],
        ]);

        $user = $request->user();
        $user->fill($data)->save();

        return response()->json(['user' => $user->fresh(['roles', 'roleProfiles.role'])]);
    }

    /**
     * Attach an additional role to the current user.
     */
    public function attachRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', Rule::in(['entrepreneur', 'investor', 'government', 'jobseeker', 'mentor'])],
        ]);

        $user = $request->user();

        if ($user->hasRole($data['slug'])) {
            return response()->json(['message' => 'Vous possédez déjà ce rôle.'], 409);
        }

        $user->assignRole($data['slug']);

        return response()->json([
            'user' => $user->fresh(['roles', 'roleProfiles.role']),
            'added' => $data['slug'],
        ]);
    }

    /**
     * Detach a role from the current user.
     */
    public function detachRole(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole($slug)) {
            return response()->json(['message' => "Rôle non attribué."], 404);
        }

        // Safeguard: keep at least one role
        if ($user->roles()->count() <= 1) {
            return response()->json(['message' => "Vous devez conserver au moins un rôle."], 422);
        }

        $user->removeRole($slug);

        return response()->json(['user' => $user->fresh(['roles', 'roleProfiles.role'])]);
    }

    /**
     * Switch the "active role" (context/lens).
     */
    public function setActiveRole(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!$user->hasRole($data['slug'])) {
            return response()->json(['message' => "Rôle non attribué."], 403);
        }

        $user->forceFill(['active_role_slug' => $data['slug']])->save();

        return response()->json(['user' => $user->fresh(['roles', 'roleProfiles.role'])]);
    }
}
