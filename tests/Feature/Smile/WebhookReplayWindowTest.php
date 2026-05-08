<?php

namespace Tests\Feature\Smile;

use App\Services\SmileIdentity\SmileSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Audit fix 2026-05 — replay-window enforcement on Smile webhook.
 *
 * VerifySmileSignature now rejects:
 *   - timestamps older than smile.webhook.replay_window_seconds (default 300)
 *   - timestamps more than smile.webhook.clock_skew_seconds in the future (60s)
 *   - malformed / missing timestamps and signatures
 *
 * The signature itself is HMAC-SHA256 of the timestamp string, so the
 * attacker-replay scenario uses a real (timestamp, signature) pair captured
 * out of band — but stale.
 */
class WebhookReplayWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'smile.api_key'                          => 'fixed-test-key-replay',
            'smile.partner_id'                       => 'partner-replay',
            'smile.webhook.replay_window_seconds'    => 300,
            'smile.webhook.clock_skew_seconds'       => 60,
        ]);
        Bus::fake(); // we only care about the middleware decision here
    }

    /** Build a payload signed at the given Carbon time. */
    protected function payloadSignedAt(Carbon $when): array
    {
        $sig = SmileSignature::generate($when);

        return [
            'timestamp'        => $sig['timestamp'],
            'signature'        => $sig['signature'],
            'SmileJobID'       => 'SMILE-REPLAY-001',
            'ResultCode'       => '0810',
            'PartnerParams'    => ['job_id' => 'job-1', 'job_type' => 1, 'user_id' => '1'],
        ];
    }

    public function test_fresh_timestamp_passes(): void
    {
        $resp = $this->postJson(
            '/api/v1/webhooks/smile-identity',
            $this->payloadSignedAt(Carbon::now()),
        );
        $resp->assertStatus(200);
    }

    public function test_timestamp_within_window_passes(): void
    {
        // 4 minutes ago — well within the 5-minute window.
        $resp = $this->postJson(
            '/api/v1/webhooks/smile-identity',
            $this->payloadSignedAt(Carbon::now()->subMinutes(4)),
        );
        $resp->assertStatus(200);
    }

    public function test_stale_timestamp_is_rejected_as_replay(): void
    {
        // 6 minutes ago — outside the window.
        $resp = $this->postJson(
            '/api/v1/webhooks/smile-identity',
            $this->payloadSignedAt(Carbon::now()->subMinutes(6)),
        );
        $resp->assertStatus(401)->assertJsonPath('reason', 'stale_timestamp');
    }

    public function test_far_past_timestamp_is_rejected(): void
    {
        $resp = $this->postJson(
            '/api/v1/webhooks/smile-identity',
            $this->payloadSignedAt(Carbon::now()->subDays(7)),
        );
        $resp->assertStatus(401)->assertJsonPath('reason', 'stale_timestamp');
    }

    public function test_small_clock_skew_in_future_is_accepted(): void
    {
        // 30 s in the future — within the 60 s skew tolerance.
        $resp = $this->postJson(
            '/api/v1/webhooks/smile-identity',
            $this->payloadSignedAt(Carbon::now()->addSeconds(30)),
        );
        $resp->assertStatus(200);
    }

    public function test_far_future_timestamp_is_rejected(): void
    {
        // 5 minutes in the future — beyond skew tolerance.
        $resp = $this->postJson(
            '/api/v1/webhooks/smile-identity',
            $this->payloadSignedAt(Carbon::now()->addMinutes(5)),
        );
        $resp->assertStatus(401)->assertJsonPath('reason', 'future_timestamp');
    }

    public function test_malformed_timestamp_is_rejected(): void
    {
        $sig = SmileSignature::generate();
        $resp = $this->postJson('/api/v1/webhooks/smile-identity', [
            'timestamp'     => 'not-an-iso-date',
            'signature'     => $sig['signature'],
            'SmileJobID'    => 'X',
            'PartnerParams' => ['job_id' => 'x', 'user_id' => '1', 'job_type' => 1],
        ]);
        $resp->assertStatus(401)->assertJsonPath('reason', 'malformed_timestamp_or_signature');
    }

    public function test_missing_signature_is_rejected(): void
    {
        $resp = $this->postJson('/api/v1/webhooks/smile-identity', [
            'timestamp' => Carbon::now()->toISOString(),
            'SmileJobID' => 'X',
            'PartnerParams' => ['job_id' => 'x', 'user_id' => '1', 'job_type' => 1],
        ]);
        $resp->assertStatus(401)->assertJsonPath('reason', 'malformed_timestamp_or_signature');
    }

    public function test_replay_attack_with_valid_old_signature_is_blocked(): void
    {
        // Capture a valid pair when the user signed up — replay it later.
        $captured = $this->payloadSignedAt(Carbon::now()->subHour());

        // Even with the legitimate signature, the timestamp is stale.
        $resp = $this->postJson('/api/v1/webhooks/smile-identity', $captured);
        $resp->assertStatus(401)->assertJsonPath('reason', 'stale_timestamp');
    }
}
