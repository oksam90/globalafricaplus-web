<?php

namespace Tests\Feature\Smile;

use App\Events\AMLFlagged;
use App\Listeners\ReportSuspiciousActivity;
use App\Models\AMLScreening;
use App\Models\PaymentLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 5 — direct tests of the ReportSuspiciousActivity listener
 * (independent of the controller, so we exercise edge cases — idempotence,
 * non-reportable inputs, sanctions vs. critical-only paths).
 */
class ReportSuspiciousActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function makeScreening(User $user, array $attrs): AMLScreening
    {
        return AMLScreening::create(array_merge([
            'user_id'             => $user->id,
            'full_name_screened'  => $user->name,
            'countries'           => ['SN'],
            'birth_year'          => '1990',
            'sanctions_match'     => false,
            'pep_match'           => false,
            'adverse_media_match' => false,
            'risk_level'          => 'low',
        ], $attrs));
    }

    public function test_does_nothing_when_screening_has_no_match(): void
    {
        $user = User::factory()->create(['aml_status' => 'clear']);
        $s = $this->makeScreening($user, []);

        (new ReportSuspiciousActivity())->handle(new AMLFlagged($user, $s));

        $s->refresh();
        $user->refresh();
        $this->assertFalse((bool) $s->auto_reported);
        $this->assertSame('clear', $user->aml_status);
        $this->assertSame(0, PaymentLog::where('gateway', 'centif')->count());
    }

    public function test_does_nothing_for_pep_only(): void
    {
        $user = User::factory()->create(['aml_status' => 'clear']);
        $s = $this->makeScreening($user, ['pep_match' => true, 'risk_level' => 'high']);

        (new ReportSuspiciousActivity())->handle(new AMLFlagged($user, $s));

        $s->refresh();
        $user->refresh();
        $this->assertFalse((bool) $s->auto_reported, 'PEP alone is not auto-reportable');
        $this->assertSame('clear', $user->aml_status, 'PEP alone does not block — compliance reviews manually');
    }

    public function test_writes_centif_log_and_blocks_user_on_sanctions_match(): void
    {
        $user = User::factory()->create(['aml_status' => 'clear']);
        $s = $this->makeScreening($user, [
            'sanctions_match' => true,
            'risk_level'      => 'critical',
        ]);

        (new ReportSuspiciousActivity())->handle(new AMLFlagged($user, $s));

        $s->refresh();
        $user->refresh();
        $this->assertTrue((bool) $s->auto_reported);
        $this->assertSame('blocked', $user->aml_status);

        $log = PaymentLog::where('gateway', 'centif')->first();
        $this->assertNotNull($log);
        $this->assertSame('compliance.suspicious_activity_report', $log->event_type);
        $this->assertSame('outbound', $log->direction);
        $this->assertSame(202, $log->status_code);
        $this->assertSame('mock', $log->payload['mode']);
    }

    public function test_writes_centif_log_for_critical_risk_even_without_sanctions(): void
    {
        // Hypothetical: critical risk derived from heuristics other than sanctions.
        $user = User::factory()->create(['aml_status' => 'clear']);
        $s = $this->makeScreening($user, [
            'pep_match'      => true,
            'adverse_media_match' => true,
            'risk_level'     => 'critical',
        ]);

        (new ReportSuspiciousActivity())->handle(new AMLFlagged($user, $s));

        $s->refresh();
        $user->refresh();
        $this->assertTrue((bool) $s->auto_reported);
        // Critical-without-sanctions does NOT auto-block — only sanctions does.
        $this->assertSame('clear', $user->aml_status);
        $this->assertSame(1, PaymentLog::where('gateway', 'centif')->count());
    }

    public function test_idempotent_when_already_reported(): void
    {
        $user = User::factory()->create(['aml_status' => 'blocked']);
        $s = $this->makeScreening($user, [
            'sanctions_match' => true,
            'risk_level'      => 'critical',
            'auto_reported'   => true, // already reported in a prior run
        ]);

        (new ReportSuspiciousActivity())->handle(new AMLFlagged($user, $s));

        $this->assertSame(0, PaymentLog::where('gateway', 'centif')->count(),
            'second invocation must not re-emit a CENTIF log');
    }
}
