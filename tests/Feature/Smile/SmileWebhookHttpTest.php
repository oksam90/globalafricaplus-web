<?php

namespace Tests\Feature\Smile;

use App\Models\KYCVerification;
use App\Models\User;
use App\Services\SmileIdentity\SmileSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Sprint 6 — full HTTP integration tests for the Smile webhook pipeline:
 *
 *   client → /api/v1/webhooks/smile-identity
 *          → VerifySmileSignature middleware
 *          → SmileWebhookController@handle
 *          → ProcessSmileCallback job
 *
 * Covers spec test scenarios T-12 (invalid signature → 401) and T-13
 * (duplicate callback → 200 + no-op).
 */
class SmileWebhookHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'smile.api_key'           => 'fixed-test-key-for-http',
            'smile.partner_id'        => 'partner-test',
            'smile.kyc_expiry_months' => 24,
        ]);
    }

    /** Build a callback payload with a freshly-signed envelope. */
    protected function signedPayload(string $partnerJobId, string $smileJobId, string $resultCode = '0810', int $jobType = 1): array
    {
        $sig = SmileSignature::generate();

        return [
            'timestamp'        => $sig['timestamp'],
            'signature'        => $sig['signature'],
            'SmileJobID'       => $smileJobId,
            'ResultCode'       => $resultCode,
            'ResultText'       => 'Approved',
            'ConfidenceValue'  => '95.5',
            'PartnerParams'    => [
                'user_id'  => 'user-irrelevant',
                'job_id'   => $partnerJobId,
                'job_type' => $jobType,
            ],
            'Actions'          => [
                'Selfie_Check'   => 'Passed',
                'Liveness_Check' => 'Passed',
                'Register_Selfie' => 'Approved',
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // T-12 — invalid HMAC signature → 401, no job dispatched
    // ─────────────────────────────────────────────────────────────────────
    public function test_t12_rejects_request_with_tampered_signature(): void
    {
        Bus::fake();

        $sig = SmileSignature::generate();
        $payload = [
            'timestamp'   => $sig['timestamp'],
            'signature'   => $sig['signature'] . 'TAMPERED',
            'SmileJobID'  => 'SMILE-T12',
            'ResultCode'  => '0810',
            'PartnerParams' => ['user_id' => 'u', 'job_id' => 'j-t12', 'job_type' => 1],
        ];

        $this->postJson('/api/v1/webhooks/smile-identity', $payload)
             ->assertStatus(401)
             ->assertJsonPath('message', 'Unauthorized');

        Bus::assertNothingDispatched();
    }

    public function test_t12_rejects_request_with_missing_signature(): void
    {
        Bus::fake();

        $this->postJson('/api/v1/webhooks/smile-identity', [
            'SmileJobID'    => 'SMILE-NOSIG',
            'ResultCode'    => '0810',
            'PartnerParams' => ['user_id' => 'u', 'job_id' => 'j-nosig', 'job_type' => 1],
        ])->assertStatus(401);

        Bus::assertNothingDispatched();
    }

    public function test_t12_rejects_request_with_missing_timestamp(): void
    {
        Bus::fake();

        $this->postJson('/api/v1/webhooks/smile-identity', [
            'signature'     => 'anything',
            'SmileJobID'    => 'SMILE-NOTS',
            'ResultCode'    => '0810',
            'PartnerParams' => ['user_id' => 'u', 'job_id' => 'j-nots', 'job_type' => 1],
        ])->assertStatus(401);

        Bus::assertNothingDispatched();
    }

    // ─────────────────────────────────────────────────────────────────────
    // T-12 — webhook is exempt from CSRF (server-to-server callback)
    // ─────────────────────────────────────────────────────────────────────
    public function test_webhook_is_csrf_exempt(): void
    {
        // Re-enable CSRF middleware (Laravel disables it for tests by default).
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

        $payload = $this->signedPayload('j-csrf', 'SMILE-CSRF');

        // The request must reach the controller (200) instead of being blocked
        // for a missing CSRF token. We do NOT pass a CSRF token here.
        $this->postJson('/api/v1/webhooks/smile-identity', $payload)
             ->assertStatus(200);
    }

    // ─────────────────────────────────────────────────────────────────────
    // T-12 — bonus: payload missing both job identifiers → 422
    // ─────────────────────────────────────────────────────────────────────
    public function test_returns_422_when_no_job_identifiers_present(): void
    {
        $sig = SmileSignature::generate();

        $this->postJson('/api/v1/webhooks/smile-identity', [
            'timestamp' => $sig['timestamp'],
            'signature' => $sig['signature'],
            // No SmileJobID, no PartnerParams.
        ])->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────────────────────
    // T-13 — duplicate callback for an already-finalised verification
    // ─────────────────────────────────────────────────────────────────────
    public function test_t13_duplicate_callback_returns_200_without_re_dispatch(): void
    {
        $user = User::factory()->create(['kyc_level' => 'basic']);

        // Pre-seed a finalised verification — simulates a callback we already
        // processed before the duplicate arrives.
        KYCVerification::create([
            'user_id'        => $user->id,
            'partner_job_id' => 'j-dup',
            'smile_job_id'   => 'SMILE-DUP',
            'job_type'       => 'biometric_kyc',
            'country'        => 'SN',
            'id_type'        => 'NATIONAL_ID',
            'id_number_hash' => KYCVerification::hashIdNumber('A1'),
            'status'         => 'approved',
            'kyc_level_granted' => 'verified',
            'submitted_at'   => now()->subMinute(),
            'completed_at'   => now()->subMinute(),
        ]);

        Bus::fake();

        $payload = $this->signedPayload('j-dup', 'SMILE-DUP');

        $this->postJson('/api/v1/webhooks/smile-identity', $payload)
             ->assertStatus(200)
             ->assertJsonPath('message', 'Already processed.');

        // No reprocessing job dispatched.
        Bus::assertNothingDispatched();
    }

    public function test_first_callback_dispatches_job_then_duplicate_does_not(): void
    {
        $user = User::factory()->create(['kyc_level' => 'basic']);

        KYCVerification::create([
            'user_id'        => $user->id,
            'partner_job_id' => 'j-first',
            'job_type'       => 'biometric_kyc',
            'country'        => 'SN',
            'id_type'        => 'NATIONAL_ID',
            'id_number_hash' => KYCVerification::hashIdNumber('B2'),
            'status'         => 'processing', // not yet finalised
            'submitted_at'   => now(),
        ]);

        Bus::fake();

        $payload = $this->signedPayload('j-first', 'SMILE-FIRST');

        // 1st call — processing → job dispatched
        $this->postJson('/api/v1/webhooks/smile-identity', $payload)
             ->assertStatus(200)
             ->assertJsonPath('message', 'OK');

        Bus::assertDispatched(\App\Jobs\ProcessSmileCallback::class, 1);

        // Promote to terminal status (simulates the job having run between deliveries).
        KYCVerification::where('partner_job_id', 'j-first')->update([
            'status' => 'approved',
            'completed_at' => now(),
        ]);

        // 2nd call — finalised → no dispatch
        $this->postJson('/api/v1/webhooks/smile-identity', $payload)
             ->assertStatus(200)
             ->assertJsonPath('message', 'Already processed.');

        Bus::assertDispatched(\App\Jobs\ProcessSmileCallback::class, 1); // still 1, not 2
    }
}
