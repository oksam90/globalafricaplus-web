<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Usage in routes:
     *   ->middleware('role:entrepreneur')
     *   ->middleware('role:entrepreneur,mentor')   // any of these
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (empty($roles) || $user->hasAnyRole($roles)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Accès refusé : rôle requis.',
            'required_roles' => $roles,
            'your_roles' => $user->roles()->pluck('slug'),
        ], 403);
    }
}
