<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckKyc
{
    /**
     * Require KYC verification to access the route.
     *
     * Usage:
     *   ->middleware('kyc')           // requires verified or certified
     *   ->middleware('kyc:certified') // requires certified level specifically
     */
    public function handle(Request $request, Closure $next, ?string $minLevel = null): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Authentification requise.'], 401);
        }

        $kycLevel = $user->kyc_level ?? 'none';
        $levels = ['none' => 0, 'basic' => 1, 'verified' => 2, 'certified' => 3];

        $required = $minLevel ? ($levels[$minLevel] ?? 2) : 2; // default: verified
        $current = $levels[$kycLevel] ?? 0;

        if ($current < $required) {
            return response()->json([
                'message' => 'Vérification KYC requise pour accéder à cette fonctionnalité.',
                'kyc_required' => true,
                'current_level' => $kycLevel,
                'required_level' => $minLevel ?? 'verified',
            ], 403);
        }

        return $next($request);
    }
}
