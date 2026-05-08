<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'country' => ['nullable', 'string', 'max:120'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(['entrepreneur', 'investor', 'government', 'jobseeker', 'mentor'])],
            'is_diaspora' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'country' => $data['country'] ?? null,
            'is_diaspora' => $data['is_diaspora'] ?? false,
            'kyc_level' => 'none',
        ]);

        foreach ($data['roles'] as $slug) {
            $user->assignRole($slug);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => $user
                ->load(['roles', 'roleProfiles.role'])
                ->makeVisible(\App\Models\User::SELF_VISIBLE), // own session response
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'user' => Auth::user()
                ->load('roles')
                ->makeVisible(\App\Models\User::SELF_VISIBLE),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'ok']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['user' => null]);
        }

        $user->load(['roles', 'roleProfiles.role'])
            ->makeVisible(\App\Models\User::SELF_VISIBLE);

        $latestSmile = $user->latestSmileVerification();

        return response()->json([
            'user' => $user,
            'subscription' => [
                'plan_slug' => $user->currentPlanSlug(),
                'has_active' => $user->hasActiveSubscription(),
                'is_refundable' => $user->activeSubscription()?->isRefundable() ?? false,
            ],
            'kyc' => [
                'level'           => $user->kyc_level ?? 'basic',
                'is_verified'     => $user->isKycVerified(),
                'has_pending'     => $user->hasKycPending(),
                'expires_at'      => $user->kyc_expires_at,
                'aml_status'      => $user->aml_status ?? 'clear',
                'latest_status'   => $latestSmile?->status,
                'latest_job_type' => $latestSmile?->job_type,
            ],
        ]);
    }
}

