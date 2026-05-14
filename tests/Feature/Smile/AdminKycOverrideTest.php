<?php

namespace Tests\Feature\Smile;

use App\Models\KYCVerification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit fix 2026-05 — TEMPORARY admin KYC override (pending Smile ticket
 * #1757). PATCH /api/admin/users/{id} accepts kyc_level and cascades the
 * Smile-flow side effects (kyc_verified_at, kyc_expires_at,
 * kyc_verification_id) so the kyc.smile:verified middleware lets the
 * user through.
 *
 * Every override writes a KYCVerification audit row with
 * partner_job_id="admin-override:{user}:{uuid}" and result_code="ADMOVR".
 */
class AdminKycOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrateur']);
        $this->admin = User::factory()->create(['email' => 'admin@test.local']);
        $this->admin->roles()->attach(Role::where('slug', 'admin')->value('id'));
    }

    public function test_admin_can_promote_user_to_verified_and_audit_row_is_created(): void
    {
        $target = User::factory()->create([
            'kyc_level'       => 'basic',
            'kyc_verified_at' => null,
            'kyc_expires_at'  => null,
        ]);

        $resp = $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$target->id}", [
                'kyc_level'           => 'verified',
                'kyc_override_reason' => 'Compte test interne — bypass Smile sandbox',
            ]);

        $resp->assertOk()
            ->assertJsonPath('kyc_override', true)
            ->assertJsonPath('user.kyc_level', 'verified');

        $target->refresh();
        $this->assertSame('verified', $target->kyc_level);
        $this->assertNotNull($target->kyc_verified_at);
        $this->assertNotNull($target->kyc_expires_at);
        $this->assertTrue($target->kyc_expires_at->isFuture());
        $this->assertNotNull($target->kyc_verification_id);

        $kv = KYCVerification::firstOrFail();
        $this->assertSame($target->id, $kv->user_id);
        $this->assertSame('approved', $kv->status);
        $this->assertSame('ADMOVR', $kv->result_code);
        $this->assertSame('verified', $kv->kyc_level_granted);
        $this->assertStringStartsWith('admin-override:', $kv->partner_job_id);

        // Payload metadata
        $payload = $kv->callback_payload;
        $this->assertTrue($payload['override']);
        $this->assertSame($this->admin->id, $payload['admin_id']);
        $this->assertSame('Compte test interne — bypass Smile sandbox', $payload['reason']);
    }

    public function test_promoting_to_certified_uses_that_tier_in_user_and_audit(): void
    {
        $target = User::factory()->create(['kyc_level' => 'basic']);

        $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$target->id}", ['kyc_level' => 'certified'])
            ->assertOk();

        $this->assertSame('certified', $target->fresh()->kyc_level);
        $this->assertSame('certified', KYCVerification::firstOrFail()->kyc_level_granted);
    }

    public function test_downgrading_clears_dates_and_audit_pointer(): void
    {
        $verification = null;

        // First promote so we have something to downgrade
        $target = User::factory()->create(['kyc_level' => 'basic']);
        $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$target->id}", ['kyc_level' => 'verified'])
            ->assertOk();
        $target->refresh();
        $this->assertNotNull($target->kyc_verification_id);

        // Then downgrade
        $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$target->id}", ['kyc_level' => 'basic'])
            ->assertOk()
            ->assertJsonPath('kyc_override', true);

        $target->refresh();
        $this->assertSame('basic', $target->kyc_level);
        $this->assertNull($target->kyc_verified_at);
        $this->assertNull($target->kyc_expires_at);
        $this->assertNull($target->kyc_verification_id);

        // Audit row stays in place — downgrade doesn't erase history.
        $this->assertSame(1, KYCVerification::count());
    }

    public function test_unrelated_user_updates_do_not_create_audit_row(): void
    {
        $target = User::factory()->create(['kyc_level' => 'basic', 'country' => 'CI']);

        $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$target->id}", ['country' => 'SN'])
            ->assertOk()
            ->assertJsonPath('kyc_override', false);

        $this->assertSame(0, KYCVerification::count());
        $this->assertSame('SN', $target->fresh()->country);
    }

    public function test_same_kyc_level_repost_does_not_create_duplicate_audit_row(): void
    {
        $target = User::factory()->create(['kyc_level' => 'verified']);

        $this->actingAs($this->admin)
            ->patchJson("/api/admin/users/{$target->id}", ['kyc_level' => 'verified'])
            ->assertOk();

        $this->assertSame(0, KYCVerification::count());
    }

    public function test_non_admin_cannot_call_user_update(): void
    {
        $regular = User::factory()->create();
        $target  = User::factory()->create(['kyc_level' => 'basic']);

        $this->actingAs($regular)
            ->patchJson("/api/admin/users/{$target->id}", ['kyc_level' => 'verified'])
            ->assertStatus(403);

        $this->assertSame('basic', $target->fresh()->kyc_level);
    }
}
