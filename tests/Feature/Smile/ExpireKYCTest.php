<?php

namespace Tests\Feature\Smile;

use App\Jobs\ExpireKYCVerification;
use App\Models\KYCVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 4 — T-11: 24-month KYC expiration cron must mark verifications as
 * expired and downgrade the linked user back to `basic`.
 */
class ExpireKYCTest extends TestCase
{
    use RefreshDatabase;

    public function test_expires_old_verification_and_downgrades_user(): void
    {
        $user = User::factory()->create(['kyc_level' => 'verified']);
        $kv = KYCVerification::create([
            'user_id'        => $user->id,
            'partner_job_id' => 'job-expiring',
            'job_type'       => 'biometric_kyc',
            'country'        => 'SN',
            'id_type'        => 'NATIONAL_ID',
            'id_number_hash' => KYCVerification::hashIdNumber('A1'),
            'status'         => 'approved',
            'submitted_at'   => now()->subYears(2)->subDay(),
            'completed_at'   => now()->subYears(2)->subDay(),
            'expires_at'     => now()->subDay(),
            'kyc_level_granted' => 'verified',
        ]);
        $user->update(['kyc_verification_id' => $kv->id]);

        (new ExpireKYCVerification())->handle();

        $kv->refresh();
        $user->refresh();
        $this->assertSame('expired', $kv->status);
        $this->assertSame('basic', $user->kyc_level);
        $this->assertNull($user->kyc_verification_id);
    }

    public function test_does_not_touch_still_valid_verification(): void
    {
        $user = User::factory()->create(['kyc_level' => 'certified']);
        $kv = KYCVerification::create([
            'user_id'        => $user->id,
            'partner_job_id' => 'job-fresh',
            'job_type'       => 'biometric_kyc',
            'country'        => 'SN',
            'id_type'        => 'NATIONAL_ID',
            'id_number_hash' => KYCVerification::hashIdNumber('B2'),
            'status'         => 'approved',
            'submitted_at'   => now()->subMonths(3),
            'completed_at'   => now()->subMonths(3),
            'expires_at'     => now()->addMonths(21),
            'kyc_level_granted' => 'certified',
        ]);
        $user->update(['kyc_verification_id' => $kv->id]);

        (new ExpireKYCVerification())->handle();

        $kv->refresh();
        $user->refresh();
        $this->assertSame('approved', $kv->status, 'fresh verification must stay approved');
        $this->assertSame('certified', $user->kyc_level);
    }

    public function test_does_not_downgrade_when_user_already_moved_to_other_verification(): void
    {
        $user = User::factory()->create(['kyc_level' => 'certified']);

        $oldKv = KYCVerification::create([
            'user_id'        => $user->id,
            'partner_job_id' => 'job-old',
            'job_type'       => 'biometric_kyc',
            'country'        => 'SN',
            'id_type'        => 'NATIONAL_ID',
            'id_number_hash' => KYCVerification::hashIdNumber('OLD'),
            'status'         => 'approved',
            'submitted_at'   => now()->subYears(2)->subDay(),
            'completed_at'   => now()->subYears(2)->subDay(),
            'expires_at'     => now()->subDay(),
            'kyc_level_granted' => 'verified',
        ]);

        $newKv = KYCVerification::create([
            'user_id'        => $user->id,
            'partner_job_id' => 'job-new',
            'job_type'       => 'biometric_kyc',
            'country'        => 'SN',
            'id_type'        => 'NATIONAL_ID',
            'id_number_hash' => KYCVerification::hashIdNumber('NEW'),
            'status'         => 'approved',
            'submitted_at'   => now()->subMonth(),
            'completed_at'   => now()->subMonth(),
            'expires_at'     => now()->addMonths(23),
            'kyc_level_granted' => 'certified',
        ]);

        $user->update(['kyc_verification_id' => $newKv->id]);

        (new ExpireKYCVerification())->handle();

        $user->refresh();
        $oldKv->refresh();

        $this->assertSame('expired', $oldKv->status);
        $this->assertSame('certified', $user->kyc_level, 'user already pointed at fresher verification — no downgrade');
        $this->assertSame($newKv->id, $user->kyc_verification_id);
    }
}
