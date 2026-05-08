<?php

namespace Tests\Feature\Smile;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit fix 2026-05 (§ 4.2.5) — sensitive User fields are stripped from JSON
 * by default. Endpoints that legitimately need them call ->makeVisible().
 *
 * What the leak surface looked like before:
 *   $project->load('user');  // user here exposes phone, aml_status, etc.
 *
 * What the fix guarantees:
 *   - Bare relation load: hidden by default (no leak).
 *   - GET /api/auth/me: visible (own profile).
 *   - GET /api/admin/users/{id}: visible (admin oversight).
 */
class UserHiddenFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrateur']);
    }

    /**
     * Returns the JSON shape of a user when serialised through a relation,
     * exactly like with('user') would on a public endpoint.
     */
    protected function userArrayAsRelation(User $user): array
    {
        // toArray() respects $hidden — same code path as JSON response.
        return $user->fresh()->toArray();
    }

    public function test_user_serialisation_hides_sensitive_fields_by_default(): void
    {
        $user = User::factory()->create([
            'phone'             => '+221774391398',
            'aml_status'        => 'flagged',
            'kyc_verification_id' => null,
            'selfie_registered' => true,
        ]);

        $arr = $this->userArrayAsRelation($user);

        // Sensitive — must be absent.
        $this->assertArrayNotHasKey('password',             $arr);
        $this->assertArrayNotHasKey('remember_token',       $arr);
        $this->assertArrayNotHasKey('phone',                $arr);
        $this->assertArrayNotHasKey('aml_status',           $arr);
        $this->assertArrayNotHasKey('aml_last_checked_at',  $arr);
        $this->assertArrayNotHasKey('kyc_verification_id',  $arr);
        $this->assertArrayNotHasKey('selfie_registered',    $arr);
        $this->assertArrayNotHasKey('email_verified_at',    $arr);

        // Legitimate public-profile fields — must stay.
        $this->assertArrayHasKey('id',              $arr);
        $this->assertArrayHasKey('name',            $arr);
        $this->assertArrayHasKey('email',           $arr); // intentionally still visible
        $this->assertArrayHasKey('country',         $arr);
        $this->assertArrayHasKey('avatar',          $arr);
        $this->assertArrayHasKey('kyc_level',       $arr); // public KYC badge
        $this->assertArrayHasKey('kyc_verified_at', $arr); // KYC badge timestamp
        $this->assertArrayHasKey('kyc_expires_at',  $arr);
    }

    public function test_make_visible_re_exposes_self_visible_fields(): void
    {
        $user = User::factory()->create([
            'phone'             => '+221774391398',
            'aml_status'        => 'flagged',
            'selfie_registered' => true,
        ]);

        $arr = $user->fresh()->makeVisible(User::SELF_VISIBLE)->toArray();

        $this->assertSame('+221774391398', $arr['phone']);
        $this->assertSame('flagged',       $arr['aml_status']);
        $this->assertTrue((bool) $arr['selfie_registered']);
    }

    public function test_me_endpoint_returns_phone_and_aml_for_own_profile(): void
    {
        $user = User::factory()->create([
            'phone'      => '+221774391398',
            'aml_status' => 'clear',
        ]);

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.phone', '+221774391398')
            ->assertJsonPath('user.aml_status', 'clear');
    }

    public function test_admin_user_show_returns_phone_and_aml_for_admin_oversight(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'admin')->value('id'));

        $target = User::factory()->create([
            'phone'      => '+221774391398',
            'aml_status' => 'flagged',
        ]);

        $this->actingAs($admin)
            ->getJson("/api/admin/users/{$target->id}")
            ->assertStatus(200)
            ->assertJsonPath('user.phone', '+221774391398')
            ->assertJsonPath('user.aml_status', 'flagged');
    }

    public function test_admin_user_listing_does_not_leak_phone(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'admin')->value('id'));

        User::factory()->create(['phone' => '+221774391398']);

        $resp = $this->actingAs($admin)->getJson('/api/admin/users');
        $resp->assertStatus(200);

        // The list should not include phone in any row.
        foreach ((array) $resp->json('data') as $row) {
            $this->assertArrayNotHasKey('phone', $row, 'phone leaked into admin user listing');
            $this->assertArrayNotHasKey('aml_status', $row, 'aml_status leaked into admin user listing');
        }
    }
}
