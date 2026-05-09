<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;

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

    // ─────────────────────────────────────────────────────────────────────
    //  Google OAuth (Socialite, 2026-05-09)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Kick the OAuth dance — Socialite redirects to Google's consent screen.
     * Mounted on the `web` group so the session can carry the CSRF state
     * Socialite stores between redirect and callback.
     */
    public function googleRedirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Google calls us back with an authorization code; we exchange it for
     * a profile, then either log in / link / create a local account.
     *
     * Matching strategy:
     *   1. by google_id  → existing OAuth-linked user, log in.
     *   2. by email      → existing password user, attach google_id.
     *   3. otherwise     → fresh sign-up with random password, mark email verified.
     */
    public function googleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::warning('Google OAuth callback failed', ['error' => $e->getMessage()]);
            return redirect('/connexion?oauth=failed');
        }

        $googleId = $googleUser->getId();
        $email    = $googleUser->getEmail();

        if (!$googleId || !$email) {
            return redirect('/connexion?oauth=missing_profile_data');
        }

        // 1) Already linked.
        $user = User::where('google_id', $googleId)->first();

        // 2) Same email, link the Google account in.
        if (!$user) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update([
                    'google_id'      => $googleId,
                    'oauth_provider' => 'google',
                    'avatar'         => $user->avatar ?: $googleUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            }
        }

        // 3) Brand-new user.
        if (!$user) {
            $user = User::create([
                'name'              => $googleUser->getName() ?: explode('@', $email)[0],
                'email'             => $email,
                'password'          => Hash::make(Str::random(40)), // never used
                'google_id'         => $googleId,
                'oauth_provider'    => 'google',
                'avatar'            => $googleUser->getAvatar(),
                'email_verified_at' => now(),
                'kyc_level'         => 'basic',
            ]);

            // Default role: investor (most permissive without privileges).
            $defaultRole = Role::firstOrCreate(['slug' => 'investor'], ['name' => 'Investisseur']);
            $user->roles()->syncWithoutDetaching([$defaultRole->id]);
            $user->update(['active_role_slug' => 'investor']);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Land the SPA on the dashboard. The query flag lets the frontend
        // surface a one-shot welcome toast.
        return redirect('/dashboard?oauth=google');
    }
}

