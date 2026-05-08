<?php

namespace App\Http\Middleware;

use App\Services\SmileIdentity\SmileSignature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sprint 2 + Audit fix 2026-05 — verify the HMAC signature **and** freshness
 * on inbound Smile Identity webhooks.
 *
 * Smile sends `timestamp` + `signature` in the JSON body of every callback.
 * This middleware short-circuits any request that fails verification with
 * 401 (logged), so the controller below only sees authenticated payloads.
 *
 * Freshness checks (audit § 4.2.3):
 *   1. The timestamp must parse as ISO-8601.
 *   2. It must lie within `smile.webhook.replay_window_seconds` (default 300)
 *      in the past — otherwise we treat the request as a captured replay.
 *   3. It must not be more than `smile.webhook.clock_skew_seconds` (default 60)
 *      in the future — accommodates Smile / VPS clock drift without granting
 *      attackers a forward-time window.
 */
class VerifySmileSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = (string) $request->input('timestamp', '');
        $signature = (string) $request->input('signature', '');

        // Parse the timestamp once so we can reuse it for both checks.
        $sentAt = $this->parseTimestamp($timestamp);

        if ($timestamp === '' || $signature === '' || $sentAt === null) {
            return $this->reject($request, 'malformed_timestamp_or_signature', [
                'has_ts' => $timestamp !== '',
                'has_sig' => $signature !== '',
                'parsed' => $sentAt !== null,
            ]);
        }

        // Replay-window check — reject anything older than the window or too
        // far in the future. Done **before** the HMAC check so an attacker
        // cannot grind on the signature with stale captures.
        $now      = Carbon::now();
        $windowS  = (int) config('smile.webhook.replay_window_seconds', 300);
        $skewS    = (int) config('smile.webhook.clock_skew_seconds', 60);
        $diff     = $now->getTimestamp() - $sentAt->getTimestamp();

        if ($diff > $windowS) {
            return $this->reject($request, 'stale_timestamp', [
                'sent_at'    => $sentAt->toIso8601String(),
                'age_secs'   => $diff,
                'max_age'    => $windowS,
            ]);
        }
        if ($diff < -$skewS) {
            return $this->reject($request, 'future_timestamp', [
                'sent_at'    => $sentAt->toIso8601String(),
                'ahead_secs' => -$diff,
                'max_skew'   => $skewS,
            ]);
        }

        if (!SmileSignature::confirm($timestamp, $signature)) {
            return $this->reject($request, 'bad_signature', [
                'sent_at' => $sentAt->toIso8601String(),
            ]);
        }

        return $next($request);
    }

    /**
     * Parse the ISO-8601 timestamp Smile sends. Returns null on any failure
     * (rather than throwing) so the caller can format a single 401 reply.
     */
    protected function parseTimestamp(string $timestamp): ?Carbon
    {
        if ($timestamp === '') {
            return null;
        }
        try {
            return Carbon::parse($timestamp);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function reject(Request $request, string $reason, array $context = []): Response
    {
        Log::warning('Smile webhook: rejected (' . $reason . ')', array_merge([
            'ip'           => $request->ip(),
            'payload_size' => strlen($request->getContent()),
        ], $context));

        return response()->json(['message' => 'Unauthorized', 'reason' => $reason], 401);
    }
}
