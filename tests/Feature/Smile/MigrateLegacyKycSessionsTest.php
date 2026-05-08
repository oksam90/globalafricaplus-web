<?php

namespace Tests\Feature\Smile;

use App\Models\KYCVerification;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Audit 2026-05 — validates the kyc:migrate-sessions one-shot command.
 * Targets a SQLite in-memory DB so we can keep the legacy kyc_sessions
 * schema alive long enough to migrate it, even though the prod migration
 * will drop it shortly after.
 */
class MigrateLegacyKycSessionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The drop-table migration runs in test setup, so we recreate the bare
     * minimum schema this test needs. Mirrors the original IDnorm columns we
     * actually read from in MigrateLegacyKycSessions::handle().
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('kyc_sessions')) {
            Schema::create('kyc_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('provider')->default('idnorm');
                $table->string('status')->default('pending');
                $table->string('document_type')->nullable();
                $table->string('document_number')->nullable();
                $table->string('country')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_dry_run_does_not_create_kyc_verifications(): void
    {
        $user = User::factory()->create(['kyc_level' => 'basic']);
        DB::table('kyc_sessions')->insert([
            'user_id'         => $user->id,
            'provider'        => 'idnorm',
            'status'          => 'verified',
            'document_type'   => 'cni',
            'document_number' => 'SN-12345',
            'country'         => 'SN',
            'verified_at'     => now()->subMonth(),
            'created_at'      => now()->subMonth(),
            'updated_at'      => now()->subMonth(),
        ]);

        $this->artisan('kyc:migrate-sessions')->assertExitCode(0);

        $this->assertSame(0, KYCVerification::count());
        $user->refresh();
        $this->assertSame('basic', $user->kyc_level, 'dry-run must not upgrade');
    }

    public function test_apply_imports_verified_sessions_and_upgrades_user(): void
    {
        $user = User::factory()->create(['kyc_level' => 'basic']);
        DB::table('kyc_sessions')->insert([
            'user_id'         => $user->id,
            'provider'        => 'idnorm',
            'status'          => 'verified',
            'document_type'   => 'passport',
            'document_number' => 'XX-987',
            'country'         => 'sn',
            'verified_at'     => now()->subMonths(3),
            'created_at'      => now()->subMonths(3),
            'updated_at'      => now()->subMonths(3),
        ]);

        $this->artisan('kyc:migrate-sessions', ['--apply' => true])->assertExitCode(0);

        $kv = KYCVerification::firstOrFail();
        $this->assertSame('approved', $kv->status);
        $this->assertSame('basic_kyc', $kv->job_type);
        $this->assertSame('PASSPORT', $kv->id_type);
        $this->assertSame('SN', $kv->country, 'country must be uppercased');
        $this->assertSame('verified', $kv->kyc_level_granted);
        $this->assertNotNull($kv->expires_at);

        $user->refresh();
        $this->assertSame('verified', $user->kyc_level);
        $this->assertSame($kv->id, $user->kyc_verification_id);
    }

    public function test_apply_is_idempotent(): void
    {
        $user = User::factory()->create(['kyc_level' => 'basic']);
        DB::table('kyc_sessions')->insert([
            'user_id'         => $user->id,
            'provider'        => 'idnorm',
            'status'          => 'verified',
            'document_type'   => 'cni',
            'document_number' => 'AAA',
            'country'         => 'CI',
            'verified_at'     => now()->subDays(10),
            'created_at'      => now()->subDays(10),
            'updated_at'      => now()->subDays(10),
        ]);

        $this->artisan('kyc:migrate-sessions', ['--apply' => true])->assertExitCode(0);
        $this->artisan('kyc:migrate-sessions', ['--apply' => true])->assertExitCode(0);

        $this->assertSame(1, KYCVerification::count(), 'second run must not duplicate');
    }

    public function test_does_not_downgrade_a_certified_user(): void
    {
        $user = User::factory()->create([
            'kyc_level'      => 'certified',
            'kyc_expires_at' => now()->addYear(),
        ]);
        DB::table('kyc_sessions')->insert([
            'user_id'         => $user->id,
            'provider'        => 'idnorm',
            'status'          => 'verified',
            'document_type'   => 'cni',
            'document_number' => 'KEEP-CERT',
            'country'         => 'SN',
            'verified_at'     => now()->subMonths(6),
            'created_at'      => now()->subMonths(6),
            'updated_at'      => now()->subMonths(6),
        ]);

        $this->artisan('kyc:migrate-sessions', ['--apply' => true])->assertExitCode(0);

        $user->refresh();
        $this->assertSame('certified', $user->kyc_level, 'certified tier must not be silently downgraded');
        $this->assertSame(1, KYCVerification::count(), 'verification row still imported for audit trail');
    }

    public function test_imports_rejected_sessions_without_upgrading(): void
    {
        $user = User::factory()->create(['kyc_level' => 'basic']);
        DB::table('kyc_sessions')->insert([
            'user_id'         => $user->id,
            'provider'        => 'idnorm',
            'status'          => 'rejected',
            'document_type'   => 'cni',
            'document_number' => 'BAD-1',
            'country'         => 'SN',
            'verified_at'     => null,
            'created_at'      => now()->subWeek(),
            'updated_at'      => now()->subWeek(),
        ]);

        $this->artisan('kyc:migrate-sessions', ['--apply' => true])->assertExitCode(0);

        $kv = KYCVerification::firstOrFail();
        $this->assertSame('rejected', $kv->status);
        $this->assertSame('0812', $kv->result_code);
        $this->assertNull($kv->kyc_level_granted);

        $user->refresh();
        $this->assertSame('basic', $user->kyc_level);
    }
}
