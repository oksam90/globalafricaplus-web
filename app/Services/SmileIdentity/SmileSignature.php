<?php

namespace App\Services\SmileIdentity;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * HMAC-SHA256 signature for Smile Identity v1 endpoints and webhook callbacks.
 *
 * Spec § 4.2 — outbound payloads must include `timestamp` + `signature` where
 *   signature = base64( hmac_sha256( timestamp, api_key ) )
 *
 * Inbound webhooks present the same pair; verify with `confirm()` using
 * `hash_equals` (timing-safe).
 */
final class SmileSignature
{
    /**
     * Generate a fresh (timestamp, signature) pair to attach to an outbound request.
     *
     * Smile Identity recomputes the HMAC on their side using the timestamp
     * string we send, then compares to our signature. They expect the
     * .NET-style format below — UTC, exactly 3-digit milliseconds, literal Z:
     *
     *   yyyy-MM-dd'T'HH:mm:ss.fffK     →  2026-05-14T00:19:00.123Z
     *
     * Carbon::toISOString() returns 6-digit microseconds (.123456Z), which
     * Smile's parser re-serialises to 3-digit millis before recomputing the
     * HMAC, breaking signature equality and surfacing as error 2205
     * ("You are not authorized to do that"). Confirmed with Smile support
     * ticket #1757 on 2026-05-13.
     *
     * @return array{timestamp: string, signature: string}
     */
    public static function generate(?Carbon $now = null): array
    {
        $apiKey    = (string) config('smile.api_key');
        $partnerId = (string) config('smile.partner_id');

        if ($apiKey === '') {
            throw new RuntimeException('SMILE_API_KEY is not configured.');
        }
        if ($partnerId === '') {
            throw new RuntimeException('SMILE_PARTNER_ID is not configured.');
        }

        $timestamp = ($now ?? Carbon::now())
            ->utc()
            ->format('Y-m-d\TH:i:s.v\Z');

        // Audit fix 2026-05-17 — the HMAC message is NOT the timestamp alone.
        // The official Smile Identity PHP SDK (smile-identity/smile-identity-core
        // v4.0.3, lib/Signature.php) computes:
        //
        //     message   = timestamp . partner_id . "sid_request"
        //     signature = base64( hmac_sha256(message, api_key) )
        //
        // Our previous code signed only the timestamp, which is why every call
        // returned HTTP 400 + code 2205 ("not authorized") despite the API key,
        // partner_id, IPv4 origin and SDK version being correct. Three weeks of
        // back-and-forth with support (ticket #1757) and the actual culprit was
        // here all along.
        $message   = $timestamp . $partnerId . 'sid_request';
        $signature = base64_encode(hash_hmac('sha256', $message, $apiKey, true));

        return [
            'timestamp' => $timestamp,
            'signature' => $signature,
        ];
    }

    /**
     * Verify a callback signature against the local API key.
     * Returns false on any mismatch; never throws.
     */
    public static function confirm(string $timestamp, string $signature): bool
    {
        $apiKey    = (string) config('smile.api_key');
        $partnerId = (string) config('smile.partner_id');
        if ($apiKey === '' || $partnerId === '' || $timestamp === '' || $signature === '') {
            return false;
        }

        // Symmetric to generate(): same `timestamp . partner_id . "sid_request"`
        // message. Smile's outbound webhook signature uses this exact formula.
        $message  = $timestamp . $partnerId . 'sid_request';
        $expected = base64_encode(hash_hmac('sha256', $message, $apiKey, true));

        return hash_equals($expected, $signature);
    }
}
