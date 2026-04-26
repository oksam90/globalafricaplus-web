<?php

namespace App\Http\Middleware;

use App\Models\PaymentLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the PayDunya IPN webhook signature.
 *
 * PayDunya sends `data.hash` which must equal SHA-512 of the master key.
 * Every attempt (valid or not) is written to `payment_logs` for audit (DPA §10).
 */
class VerifyPayDunyaWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $receivedHash = $request->input('data.hash');
        $masterKey    = config('paydunya.master_key');
        $expectedHash = $masterKey ? hash('sha512', $masterKey) : null;

        $valid = is_string($receivedHash)
            && is_string($expectedHash)
            && hash_equals($expectedHash, $receivedHash);

        // Audit log (always, regardless of outcome).
        try {
            PaymentLog::create([
                'gateway'         => 'paydunya',
                'event_type'      => 'ipn.received',
                'direction'       => 'inbound',
                'payload'         => $request->all(),
                'ip_address'      => $request->ip(),
                'user_agent'      => substr((string) $request->userAgent(), 0, 500),
                'signature'       => is_string($receivedHash) ? substr($receivedHash, 0, 255) : null,
                'signature_valid' => $valid,
                'http_method'     => $request->method(),
                'endpoint'        => $request->path(),
                'status_code'     => $valid ? 200 : 401,
                'gateway_reference' => $request->input('data.invoice.token'),
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PayDunya webhook audit log failed', ['message' => $e->getMessage()]);
        }

        if (!$valid) {
            Log::warning('PayDunya webhook: invalid signature', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
            return response('Unauthorized', 401);
        }

        return $next($request);
    }
}
