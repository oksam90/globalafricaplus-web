<?php

namespace App\Http\Middleware;

use App\Services\SmileIdentity\SmileSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 2 — verify the HMAC signature on inbound Smile Identity webhooks.
 *
 * Smile sends `timestamp` + `signature` in the JSON body of every callback.
 * This middleware short-circuits any request that fails verification with
 * 401 (logged), so the controller below only sees authenticated payloads.
 *
 * Bypass for the sandbox loopback IS NOT done here — it must happen in the
 * controller if explicitly requested.
 */
class VerifySmileSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = (string) $request->input('timestamp', '');
        $signature = (string) $request->input('signature', '');

        if ($timestamp === '' || $signature === '' || !SmileSignature::confirm($timestamp, $signature)) {
            Log::warning('Smile webhook: invalid signature', [
                'ip'           => $request->ip(),
                'has_ts'       => $timestamp !== '',
                'has_sig'      => $signature !== '',
                'payload_size' => strlen($request->getContent()),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
