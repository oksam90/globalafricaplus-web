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
        $apiKey = (string) config('smile.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('SMILE_API_KEY is not configured.');
        }

        $timestamp = ($now ?? Carbon::now())
            ->utc()
            ->format('Y-m-d\TH:i:s.v\Z');

        $signature = base64_encode(hash_hmac('sha256', $timestamp, $apiKey, true));

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
        $apiKey = (string) config('smile.api_key');
        if ($apiKey === '' || $timestamp === '' || $signature === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp, $apiKey, true));

        return hash_equals($expected, $signature);
    }
}
