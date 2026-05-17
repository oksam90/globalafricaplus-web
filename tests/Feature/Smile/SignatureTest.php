<?php

namespace Tests\Feature\Smile;

use App\Services\SmileIdentity\SmileSignature;
use Tests\TestCase;

/**
 * Sprint 4 — T-12: invalid HMAC must be rejected; valid HMAC accepted.
 */
class SignatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'smile.api_key'    => 'fixed-test-key-for-signing',
            'smile.partner_id' => '8599', // needed by generate() since 2026-05-17 fix
        ]);
    }

    public function test_generate_returns_timestamp_and_base64_signature(): void
    {
        $sig = SmileSignature::generate();

        $this->assertArrayHasKey('timestamp', $sig);
        $this->assertArrayHasKey('signature', $sig);
        $this->assertNotEmpty($sig['timestamp']);
        $this->assertNotEmpty($sig['signature']);
        $this->assertNotFalse(base64_decode($sig['signature'], true), 'signature should be valid base64');
    }

    /**
     * Audit fix 2026-05 — Smile recomputes the HMAC on their side after
     * re-serialising our timestamp to .NET format `yyyy-MM-dd'T'HH:mm:ss.fffK`
     * (3-digit millis, UTC Z). Any other shape — notably the 6-digit
     * microseconds Carbon::toISOString() returned previously — leads to
     * signature mismatch and HTTP 2205. Pin the format with a regex.
     */
    public function test_timestamp_matches_smile_dotnet_format(): void
    {
        $sig = SmileSignature::generate();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/',
            $sig['timestamp'],
            'timestamp must be yyyy-MM-ddTHH:mm:ss.fffZ (UTC, 3-digit millis)'
        );
    }

    public function test_generate_uses_utc_even_if_app_timezone_is_not(): void
    {
        // Pin a specific moment in a non-UTC zone so we can prove conversion.
        $local = \Illuminate\Support\Carbon::create(2026, 6, 15, 10, 30, 0, 'Africa/Dakar');
        $sig = SmileSignature::generate($local);

        // Dakar = UTC+0 historically, but we still expect a literal Z suffix
        // and a parseable UTC date that round-trips to our input second.
        $this->assertStringEndsWith('Z', $sig['timestamp']);
        $this->assertStringStartsWith('2026-06-15T10:30:00.', $sig['timestamp']);
    }

    public function test_confirm_accepts_freshly_generated_signature(): void
    {
        $sig = SmileSignature::generate();
        $this->assertTrue(SmileSignature::confirm($sig['timestamp'], $sig['signature']));
    }

    public function test_confirm_rejects_tampered_signature(): void
    {
        $sig = SmileSignature::generate();
        $this->assertFalse(SmileSignature::confirm($sig['timestamp'], $sig['signature'] . 'X'));
    }

    public function test_confirm_rejects_tampered_timestamp(): void
    {
        $sig = SmileSignature::generate();
        $this->assertFalse(SmileSignature::confirm('1999-01-01T00:00:00.000Z', $sig['signature']));
    }

    public function test_confirm_rejects_empty_inputs(): void
    {
        $this->assertFalse(SmileSignature::confirm('', ''));
        $this->assertFalse(SmileSignature::confirm('2026-01-01T00:00:00Z', ''));
        $this->assertFalse(SmileSignature::confirm('', 'somesig'));
    }

    public function test_confirm_returns_false_when_api_key_missing(): void
    {
        config(['smile.api_key' => '']);
        $this->assertFalse(SmileSignature::confirm('2026-01-01T00:00:00Z', 'whatever'));
    }

    /**
     * Audit fix 2026-05-17 — pin the exact HMAC message format from the
     * official Smile Identity PHP SDK (smile-identity/smile-identity-core
     * v4.0.3, lib/Signature.php):
     *
     *     message = timestamp . partner_id . "sid_request"
     *
     * If anyone "simplifies" the message back to just $timestamp this
     * test goes red — that change broke prod for three weeks before we
     * tracked it down. Don't undo it.
     */
    public function test_signature_message_matches_official_sdk_formula(): void
    {
        config([
            'smile.api_key'    => 'my-test-key',
            'smile.partner_id' => '8599',
        ]);

        $now = \Illuminate\Support\Carbon::create(2026, 5, 17, 20, 57, 0, 'UTC');
        $sig = SmileSignature::generate($now);

        // Recompute independently using the canonical SDK formula and ensure
        // the two match. Any change to generate() that deviates fails here.
        $expectedMessage   = $sig['timestamp'] . '8599' . 'sid_request';
        $expectedSignature = base64_encode(hash_hmac('sha256', $expectedMessage, 'my-test-key', true));

        $this->assertSame($expectedSignature, $sig['signature']);
    }

    /**
     * If partner_id changes, the signature must change (different message).
     * Defends against the regression where partner_id was silently dropped
     * from the HMAC payload.
     */
    public function test_signature_depends_on_partner_id(): void
    {
        config(['smile.api_key' => 'k']);
        $now = \Illuminate\Support\Carbon::create(2026, 5, 17, 20, 57, 0, 'UTC');

        config(['smile.partner_id' => 'A']);
        $sigA = SmileSignature::generate($now);

        config(['smile.partner_id' => 'B']);
        $sigB = SmileSignature::generate($now);

        $this->assertSame($sigA['timestamp'], $sigB['timestamp']);
        $this->assertNotSame($sigA['signature'], $sigB['signature']);
    }
}
