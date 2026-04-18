<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    /**
     * Ensure the authenticated user has an active paid subscription.
     *
     * Usage in routes: ->middleware('subscribed')
     * Allows: plans starter, pro, enterprise (any non-free paid plan)
     *
     * With parameter: ->middleware('subscribed:pro,enterprise')
     * Allows: only specified plan slugs
     */
    public function handle(Request $request, Closure $next, string ...$allowedPlans): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentification requise.',
                'subscription_required' => true,
            ], 401);
        }

        $currentPlan = $user->currentPlanSlug();

        // If specific plans are required, check against them
        if (!empty($allowedPlans)) {
            if (!in_array($currentPlan, $allowedPlans)) {
                return response()->json([
                    'message' => 'Cette fonctionnalité nécessite un abonnement supérieur.',
                    'subscription_required' => true,
                    'required_plans' => $allowedPlans,
                    'current_plan' => $currentPlan,
                ], 403);
            }
            return $next($request);
        }

        // Default: any non-free plan
        if ($currentPlan === 'free') {
            return response()->json([
                'message' => 'Cette fonctionnalité nécessite un abonnement. Choisissez un pack adapté à vos besoins.',
                'subscription_required' => true,
                'current_plan' => 'free',
            ], 403);
        }

        return $next($request);
    }
}
