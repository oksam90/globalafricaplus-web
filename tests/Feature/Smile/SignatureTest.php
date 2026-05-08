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
        config(['smile.api_key' => 'fixed-test-key-for-signing']);
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
}
