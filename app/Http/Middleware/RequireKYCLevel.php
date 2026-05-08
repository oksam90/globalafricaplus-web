<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 2 — guard routes by Smile-driven KYC tier (spec § 8).
 *
 * Differs from the legacy `kyc` middleware (CheckKyc) in two ways:
 *   1. It auto-downgrades a user whose `kyc_expires_at` lies in the past:
 *      the level is set back to `basic` and the request is rejected with 403.
 *   2. It returns the spec's exact JSON shape (`required_level`,
 *      `current_level`) so the SPA can route the user to the right step
 *      of the eKYC flow.
 *
 * Usage:
 *     ->middleware('kyc.smile:verified')
 *     ->middleware('kyc.smile:certified')
 */
class RequireKYCLevel
{
    /** Hierarchy used for the >= comparison. Keep aligned with config/smile.php. */
    private const RANK = ['basic' => 0, 'verified' => 1, 'certified' => 2];

    public function handle(Request $request, Closure $next, string $level = 'verified'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error'   => 'auth_required',
                'message' => 'Authentification requise.',
            ], 401);
        }

        // Normalise: legacy 'none' counts as below 'basic'.
        $currentLevel = $user->kyc_level === 'none' || $user->kyc_level === null
            ? 'basic'
            : $user->kyc_level;

        $required = self::RANK[$level] ?? self::RANK['verified'];
        $current  = self::RANK[$currentLevel] ?? -1;

        // 1. Expiration first — even a `certified` user must re-verify if past expiry.
        if ($currentLevel !== 'basic' && $user->kyc_expires_at !== null && $user->kyc_expires_at->isPast()) {
            $user->update(['kyc_level' => 'basic']);

            return response()->json([
                'error'          => 'kyc_expired',
                'required_level' => $level,
                'current_level'  => 'basic',
                'message'        => 'Votre vérification KYC a expiré. Veuillez la renouveler.',
            ], 403);
        }

        // 2. Insufficient level.
        if ($current < $required) {
            return response()->json([
                'error'          => 'kyc_insufficient',
                'required_level' => $level,
                'current_level'  => $currentLevel,
                'message'        => 'Veuillez compléter votre vérification KYC.',
            ], 403);
        }

        // 3. AML block — even at the right tier, blocked users cannot transact.
        if (($user->aml_status ?? 'clear') === 'blocked') {
            return response()->json([
                'error'          => 'aml_blocked',
                'required_level' => $level,
                'current_level'  => $currentLevel,
                'message'        => 'Votre compte est bloqué pour conformité AML. Contactez le support.',
            ], 403);
        }

        return $next($request);
    }
}
