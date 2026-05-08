<?php

namespace Tests\Feature\Smile;

use App\Events\KYCVerified;
use App\Jobs\ProcessSmileCallback;
use App\Models\KYCVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Sprint 4 — T-13: a duplicate Smile callback must be ignored once the
 * verification has reached a terminal status, AND the corresponding domain
 * event must fire only on the first successful run.
 */
class WebhookIdempotenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'smile.api_key'           => 'fixed-test-key',
            'smile.partner_id'        => 'partner-test',
            'smile.kyc_expiry_months' => 24,
        ]);
    }

    public function test_callback_promotes_pending_verification_and_fires_event_once(): void
    {
        Event::fake([KYCVerified::class]);

        $user = User::factory()->create(['kyc_level' => 'basic']);
        $kv = KYCVerification::create([
            'user_id'        => $user->id,
            'partner_job_id' => 'job-uuid-001',
            'job_type'       => 'biometric_kyc',
            'country'        => 'SN',
            'id_type'        => 'NATIONAL_ID',
            'id_number_hash' => KYCVerification::hashIdNumber('SN-test-1'),
            'status'         => 'processing',
            'submitted_at'   => now(),
        ]);

        $payload = [
            'SmileJobID'      => 'SMILE-001',
            'ResultCode'      => '0810',
            'ResultText'      => 'Approved',
            'ConfidenceValue' => '95.5',
            'PartnerParams'   => ['user_id' => (string) $user->id, 'job_id' => 'job-uuid-001', 'job_type' => 1],
            'Actions'         => ['Selfie_Check' => 'Passed', 'Register_Selfie' => 'Approved'],
        ];

        // First fire — should approve the verification and dispatch the event.
        (new ProcessSmileCallback($payload))->handle();
        $kv->refresh();
        $user->refresh();

        $this->assertSame('approved', $kv->status);
        $this->assertSame('certified', $kv->kyc_level_granted);
        $this->assertSame('certified', $user->kyc_level);
        $this->assertNotNull($user->kyc_expires_at);
        $this->assertTrue((bool) $user->selfie_registered);

        Event::assertDispatched(KYCVerified::class, 1);

        // Second fire — must be a no-op (idempotency guard).
        (new ProcessSmileCallback($payload))->handle();
        $kv->refresh();

        $this->assertSame('approved', $kv->status);
        Event::assertDispatched(KYCVerified::class, 1); // still 1, not 2
    }
}
