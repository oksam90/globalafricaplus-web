<?php

namespace Tests\Feature\Smile;

use App\Events\AMLFlagged;
use App\Models\AMLScreening;
use App\Models\PaymentLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sprint 5 — AML Check end-to-end coverage of test scenarios T-08 → T-10
 * (spec § 11) + the auto-block & suspicious-activity-report side effects.
 *
 * The Smile API is faked via Http::fake; we only assert what our system
 * persists / dispatches in response to each shape.
 */
class AmlCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'smile.api_key'           => 'fixed-test-key',
            'smile.partner_id'        => 'partner-test',
            'smile.base_url'          => 'https://testapi.smileidentity.com/v1',
            'smile.aml.strict_match'  => true,
            'smile.aml.check_pep'     => true,
            'smile.aml.check_sanctions' => true,
            'smile.aml.check_adverse_media' => true,
        ]);
    }

    /** Fake Smile's POST /v1/aml_check with a given response body. */
    protected function fakeSmileAml(array $body, int $status = 200): void
    {
        Http::fake([
            'testapi.smileidentity.com/v1/aml_check' => Http::response($body, $status),
        ]);
    }

    /** POST /api/v1/kyc/aml as the given user. */
    protected function postAml(User $user, array $payload = []): \Illuminate\Testing\TestResponse
    {
        $defaults = [
            'full_name'  => 'Aminata Diop',
            'countries'  => ['SN'],
            'birth_year' => '1990',
        ];
        return $this->actingAs($user)->postJson('/api/v1/kyc/aml', array_merge($defaults, $payload));
    }

    // ─────────────────────────────────────────────────────────────────────
    // T-08 — AML no match → low risk, no event, user stays clear
    // ─────────────────────────────────────────────────────────────────────
    public function test_t08_no_match_returns_low_risk_and_keeps_user_clear(): void
    {
        Event::fake([AMLFlagged::class]);

        $this->fakeSmileAml([
            'ResultCode'          => '1022', // No match
            'sanctions_match'     => false,
            'pep_match'           => false,
            'adverse_media_match' => false,
        ]);

        $user = User::factory()->create(['kyc_level' => 'verified', 'aml_status' => 'clear']);

        $response = $this->postAml($user);
        $response->assertCreated()
            ->assertJsonPath('risk_level', 'low')
            ->assertJsonPath('flags.sanctions',     false)
            ->assertJsonPath('flags.pep',           false)
            ->assertJsonPath('flags.adverse_media', false);

        $screening = AMLScreening::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('low', $screening->risk_level);
        $this->assertFalse($screening->sanctions_match);
        $this->assertFalse($screening->pep_match);
        $this->assertFalse($screening->adverse_media_match);
        $this->assertFalse((bool) $screening->auto_reported);

        $user->refresh();
        $this->assertSame('clear', $user->aml_status);
        $this->assertNotNull($user->aml_last_checked_at);

        Event::assertNotDispatched(AMLFlagged::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // T-09 — PEP detected → high risk, AMLFlagged dispatched, no auto-report
    // ─────────────────────────────────────────────────────────────────────
    public function test_t09_pep_match_marks_high_risk_and_dispatches_event(): void
    {
        Event::fake([AMLFlagged::class]);

        $this->fakeSmileAml([
            'ResultCode'          => '1020', // Match
            'sanctions_match'     => false,
            'pep_match'           => true,
            'adverse_media_match' => false,
            'matches'             => [
                ['type' => 'pep', 'category' => 'Senior government official', 'country' => 'SN'],
            ],
        ]);

        $user = User::factory()->create(['kyc_level' => 'verified', 'aml_status' => 'clear']);

        $response = $this->postAml($user, ['full_name' => 'John PEP']);
        $response->assertCreated()->assertJsonPath('risk_level', 'high');

        $screening = AMLScreening::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('high', $screening->risk_level);
        $this->assertTrue($screening->pep_match);
        $this->assertFalse($screening->sanctions_match);
        $this->assertFalse((bool) $screening->auto_reported, 'PEP alone must NOT auto-trigger CENTIF report');

        $user->refresh();
        // PEP-only triggers `flagged` (compliance review) but NOT `blocked`.
        $this->assertSame('clear', $user->aml_status, 'PEP-only screening keeps user clear of `flagged` per current rule');

        Event::assertDispatched(AMLFlagged::class, 1);
        Event::assertDispatched(AMLFlagged::class, fn ($event) => $event->user->id === $user->id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // T-10 — sanctions match → CRITICAL, user FLAGGED + auto-report fires
    // ─────────────────────────────────────────────────────────────────────
    public function test_t10_sanctions_match_blocks_user_and_writes_centif_report(): void
    {
        // Real listener should run (no Event::fake) so we observe the side effects.
        $this->fakeSmileAml([
            'ResultCode'          => '1020',
            'sanctions_match'     => true,
            'pep_match'           => false,
            'adverse_media_match' => false,
            'matches'             => [
                ['type' => 'sanctions', 'list' => 'OFAC SDN', 'reason' => 'Terrorism finance'],
            ],
        ]);

        $user = User::factory()->create(['kyc_level' => 'verified', 'aml_status' => 'clear']);

        $response = $this->postAml($user, ['full_name' => 'OFAC Listed Person']);
        $response->assertCreated()->assertJsonPath('risk_level', 'critical');

        $screening = AMLScreening::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('critical', $screening->risk_level);
        $this->assertTrue($screening->sanctions_match);
        $this->assertTrue((bool) $screening->auto_reported, 'sanctions match must trigger auto CENTIF report');

        $user->refresh();
        $this->assertSame('blocked', $user->aml_status, 'sanctions match must auto-block the user');

        // Audit trail: PaymentLog row with the suspicious-activity report payload.
        $log = PaymentLog::where('gateway', 'centif')
            ->where('event_type', 'compliance.suspicious_activity_report')
            ->latest()->first();
        $this->assertNotNull($log, 'CENTIF audit log row must exist');
        $this->assertSame($user->id,       $log->payload['user_id']);
        $this->assertSame($screening->id,  $log->payload['screening_id']);
        $this->assertSame('critical',      $log->payload['risk_level']);
        $this->assertTrue($log->payload['sanctions_match']);
        $this->assertSame('mock', $log->payload['mode']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Bonus — Adverse media (3+) escalates from medium to high
    // ─────────────────────────────────────────────────────────────────────
    public function test_adverse_media_with_three_or_more_hits_escalates_to_high(): void
    {
        Event::fake([AMLFlagged::class]);

        $this->fakeSmileAml([
            'ResultCode'          => '1020',
            'sanctions_match'     => false,
            'pep_match'           => false,
            'adverse_media_match' => true,
            'matches'             => [
                ['type' => 'adverse_media', 'source' => 'Reuters', 'date' => '2024-01-15'],
                ['type' => 'adverse_media', 'source' => 'BBC',     'date' => '2024-03-22'],
                ['type' => 'adverse_media', 'source' => 'AFP',     'date' => '2024-07-01'],
                ['type' => 'adverse_media', 'source' => 'Le Monde','date' => '2025-02-10'],
            ],
        ]);

        $user = User::factory()->create(['kyc_level' => 'verified']);

        $response = $this->postAml($user);
        $response->assertCreated()->assertJsonPath('risk_level', 'high');

        $screening = AMLScreening::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('high', $screening->risk_level);

        Event::assertDispatched(AMLFlagged::class);
    }

    public function test_adverse_media_single_hit_stays_medium(): void
    {
        Event::fake([AMLFlagged::class]);

        $this->fakeSmileAml([
            'ResultCode'          => '1020',
            'sanctions_match'     => false,
            'pep_match'           => false,
            'adverse_media_match' => true,
            'matches'             => [['type' => 'adverse_media', 'source' => 'Local press']],
        ]);

        $user = User::factory()->create(['kyc_level' => 'verified']);
        $response = $this->postAml($user);

        $response->assertCreated()->assertJsonPath('risk_level', 'medium');
        $this->assertSame('medium', AMLScreening::where('user_id', $user->id)->first()->risk_level);
    }
}
