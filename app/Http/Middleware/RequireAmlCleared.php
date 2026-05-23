<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optimisation point 3 — investor profile validation.
 *
 * KYC alone proves identity ; AML screening proves the user is not on a
 * sanctions / PEP / adverse-media list. To invest on the platform, the
 * user must have completed BOTH.
 *
 * This middleware enforces the AML side of the requirement (`aml_last_checked_at`
 * is set + status is not `blocked`/`flagged`). Stack it with `kyc.smile:verified`
 * to enforce the full investor-profile gate.
 *
 * Usage:
 *     ->middleware(['kyc.smile:verified', 'aml.checked'])
 */
class RequireAmlCleared
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error'   => 'auth_required',
                'message' => 'Authentification requise.',
            ], 401);
        }

        $status = $user->aml_status ?? 'clear';

        if ($status === 'blocked') {
            return response()->json([
                'error'      => 'aml_blocked',
                'aml_status' => $status,
                'message'    => 'Votre compte est bloqué pour conformité AML. Contactez le support.',
            ], 403);
        }

        if ($status === 'flagged') {
            return response()->json([
                'error'      => 'aml_flagged',
                'aml_status' => $status,
                'message'    => 'Votre dossier AML est en cours de revue. Vous pourrez investir dès la levée du flag.',
            ], 403);
        }

        if ($user->aml_last_checked_at === null) {
            return response()->json([
                'error'      => 'aml_required',
                'aml_status' => $status,
                'message'    => "Un screening AML est requis avant d'investir. Veuillez compléter votre profil investisseur.",
            ], 403);
        }

        return $next($request);
    }
}
