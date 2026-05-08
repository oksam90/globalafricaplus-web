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
     * @return array{timestamp: string, signature: string}
     */
    public static function generate(?Carbon $now = null): array
    {
        $apiKey = (string) config('smile.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('SMILE_API_KEY is not configured.');
        }

        // Smile Identity expects a fully-qualified ISO8601 string. Carbon's
        // toISOString() returns UTC with milliseconds + Z, which the API accepts.
        $timestamp = ($now ?? Carbon::now())->toISOString();

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
