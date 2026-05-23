<?php

namespace App\Support;

/**
 * Audit fix 2026-05 (§ 4.2.4) — strip / mask personally-identifiable
 * information before it lands in `payment_logs.payload` or
 * `kyc_verifications.callback_payload`.
 *
 * Goals
 *   - A future DB leak must not expose customer phone numbers, emails,
 *     ID photo S3 URLs or PDF receipts.
 *   - Forensic value preserved: gateway tokens, status codes, references,
 *     our own user_id stay intact so we can still reconstruct what
 *     happened.
 *   - Idempotent + null-safe: callers never need to special-case missing
 *     keys.
 *
 * Stable contract:
 *   - email   "aminata.diop@example.com"  → "a***@example.com"
 *   - phone   "+221774391398"             → "+22177****1398"  (last 4 kept)
 *   - long ID "1234567890123"             → "1234********3"   (mid masked)
 */
final class PiiRedactor
{
    // ────────────────────────────────────────────────────────────────────
    //  PayDunya IPN payloads (VerifyPayDunyaWebhook + ProcessPayDunyaWebhook)
    // ────────────────────────────────────────────────────────────────────

    /**
     * Filter a PayDunya IPN payload (`$request->all()`) into an audit-safe
     * shape: keep transactional metadata, strip / redact customer PII.
     */
    public static function redactPaydunyaWebhook(array $payload): array
    {
        $data = (array) ($payload['data'] ?? []);

        $invoice = (array) ($data['invoice'] ?? []);
        $customer = (array) ($data['customer'] ?? []);

        return [
            'mode'        => $data['mode']   ?? null,
            'status'      => $data['status'] ?? null,
            'response_code'    => $data['response_code']    ?? null,
            'response_text'    => $data['response_text']    ?? null,
            'invoice' => [
                'token'        => $invoice['token']        ?? null,
                'total_amount' => $invoice['total_amount'] ?? null,
                'description'  => $invoice['description']  ?? null,
                // intentionally drop invoice_url / receipt_url (signed)
            ],
            'custom_data' => self::scrubCustomData((array) ($data['custom_data'] ?? [])),
            'actions'     => $data['actions'] ?? null,
            // customer block — keep masked references only
            'customer'    => [
                'name'  => self::initials($customer['name']  ?? null),
                'email' => self::redactEmail($customer['email'] ?? null),
                'phone' => self::redactPhone($customer['phone'] ?? null),
                'country' => $customer['country'] ?? null,
            ],
        ];
    }

    /**
     * Filter the `gateway_status` (raw $status->raw from PayDunyaGateway) so
     * the inner `customer` block doesn't slip through.
     */
    public static function redactPaydunyaStatus(?array $raw): ?array
    {
        if (!$raw) {
            return null;
        }
        $copy = $raw;
        if (isset($copy['customer'])) {
            $copy['customer'] = [
                'name'  => self::initials($copy['customer']['name']  ?? null),
                'email' => self::redactEmail($copy['customer']['email'] ?? null),
                'phone' => self::redactPhone($copy['customer']['phone'] ?? null),
            ];
        }
        // signed receipt URLs leak the document itself, drop them.
        unset($copy['receipt_url'], $copy['invoice_url']);
        return $copy;
    }

    /**
     * Generic defensive scrub for mobile-money disbursement raw responses
     * (PayDunya DirectPay today; Wave/Orange Money/etc. tomorrow). Drops any
     * key that *looks* like PII so a future gateway adapter that smuggles
     * customer data through DisburseResult::$raw can't leak it into
     * payment_logs.
     *
     * Keys preserved: response_code, response_text, description, transaction_id
     * Keys dropped or masked: anything containing phone/msisdn/email/name/address
     */
    public static function redactDisburseRaw(?array $raw): array
    {
        if (!$raw) return [];

        $clean = [];
        foreach ($raw as $key => $value) {
            $lower = strtolower((string) $key);

            // Hard drop — these always carry PII or are out-of-band noise.
            if (str_contains($lower, 'email')
                || str_contains($lower, 'phone')
                || str_contains($lower, 'msisdn')
                || str_contains($lower, 'address')
                || str_contains($lower, 'customer')
            ) {
                continue;
            }

            // Soft mask — name-ish fields kept as initials for forensics.
            if (str_contains($lower, 'name')) {
                $clean[$key] = is_string($value) ? self::initials($value) : null;
                continue;
            }

            $clean[$key] = is_array($value) ? self::redactDisburseRaw($value) : $value;
        }
        return $clean;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Smile Identity callbacks (ProcessSmileCallback)
    // ────────────────────────────────────────────────────────────────────

    /**
     * Smile callbacks ship signed S3 URLs to the captured selfie + ID photo
     * (`ImageLinks`) and a signed PDF receipt (`KYCReceipt`). These are
     * short-lived but still sensitive while fresh, and pointless once
     * expired — drop them. The signature itself is also stripped (we keep
     * the result, not the proof of the result).
     */
    public static function redactSmileCallback(array $payload): array
    {
        $copy = $payload;
        unset(
            $copy['ImageLinks'],
            $copy['KYCReceipt'],
            $copy['signature'], // already validated by middleware
        );
        return $copy;
    }

    // ────────────────────────────────────────────────────────────────────
    //  CENTIF / suspicious-activity reports (ReportSuspiciousActivity)
    // ────────────────────────────────────────────────────────────────────

    /**
     * Build a redacted payload for the local audit copy of a suspicious-
     * activity report. The real CENTIF transmission carries the full
     * identity per LCB-FT requirements; what we keep on disk is the
     * minimum needed to cross-reference back to `users.id`.
     */
    public static function redactSarPayload(array $rich): array
    {
        return array_merge($rich, [
            'user_name'  => self::initials($rich['user_name']  ?? null),
            'user_email' => self::redactEmail($rich['user_email'] ?? null),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Primitive helpers (also exported for ad-hoc redaction)
    // ────────────────────────────────────────────────────────────────────

    public static function redactEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return $email ? '***' : null;
        }
        [$local, $domain] = explode('@', $email, 2);
        $first = $local !== '' ? mb_substr($local, 0, 1) : '*';
        return $first . '***@' . $domain;
    }

    public static function redactPhone(?string $phone): ?string
    {
        if (!$phone) return null;
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) <= 4) {
            return str_repeat('*', max(1, strlen($digits)));
        }
        $countryPrefix = '';
        if (str_starts_with($phone, '+')) {
            // Keep the leading `+` and the next 1-3 digits as country prefix.
            $countryPrefix = '+' . substr($digits, 0, min(3, strlen($digits) - 4));
            $digits        = substr($digits, strlen($countryPrefix) - 1);
        }
        $last4 = substr($digits, -4);
        $stars = str_repeat('*', max(1, strlen($digits) - 4));
        return $countryPrefix . $stars . $last4;
    }

    public static function initials(?string $name): ?string
    {
        if (!$name) return null;
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $out   = [];
        foreach ($parts as $part) {
            if ($part !== '') {
                $out[] = mb_substr($part, 0, 1) . '.';
            }
        }
        return $out ? implode(' ', $out) : '***';
    }

    /**
     * `custom_data` blocks are partner-controlled (we set them at checkout
     * creation). They include user_id / project_id which we want to keep,
     * but downstream forks may smuggle other PII through — drop anything
     * that *looks* like a contact field.
     */
    private static function scrubCustomData(array $custom): array
    {
        unset(
            $custom['email'],
            $custom['phone'],
            $custom['name'],
            $custom['address'],
        );
        return $custom;
    }
}
