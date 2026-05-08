<?php

namespace Tests\Feature\Smile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Audit fix 2026-05 — Rate-limiting on /v1/kyc/* and /admin/*.
 *
 * Validates:
 *   - 11th call within an hour is refused with 429 on kyc-submissions.
 *   - kyc-reads bucket allows generous polling (60/minute).
 *   - admin-read enforced on GET /admin/users.
 *   - admin-write blocks runaway loops on destructive endpoints.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear any persisted limiter state between tests so the buckets
        // start empty (matters because the cache store leaks across runs).
        RateLimiter::clear('kyc-submissions');
        RateLimiter::clear('kyc-reads');
        RateLimiter::clear('admin-read');
        RateLimiter::clear('admin-write');
    }

    public function test_kyc_status_allows_60_calls_per_minute(): void
    {
        $user = User::factory()->create(['kyc_level' => 'verified']);

        // 60 calls — all should succeed.
        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($user)
                ->getJson('/api/v1/kyc/status')
                ->assertStatus(200);
        }

        // 61st should be throttled.
        $this->actingAs($user)
            ->getJson('/api/v1/kyc/status')
            ->assertStatus(429);
    }

    public function test_kyc_basic_blocks_after_10_submissions_per_hour(): void
    {
        config(['smile.partner_id' => '', 'smile.api_key' => '']); // ensures the controller errors early w/o calling Smile
        $user = User::factory()->create();

        $payload = [
            'country'    => 'SN',
            'id_type'    => 'NATIONAL_ID',
            'id_number'  => '00000000000',
            'first_name' => 'Aminata',
            'last_name'  => 'Diop',
            'dob'        => '1990-05-15',
        ];

        // 10 submissions accepted (each will 502 because Smile config empty,
        // but rate-limiter counts hits regardless of downstream outcome).
        for ($i = 0; $i < 10; $i++) {
            $resp = $this->actingAs($user)->postJson('/api/v1/kyc/basic', $payload);
            $this->assertNotSame(429, $resp->status(), "request {$i} should not be throttled yet");
        }

        // 11th must be throttled.
        $this->actingAs($user)
            ->postJson('/api/v1/kyc/basic', $payload)
            ->assertStatus(429);
    }

    public function test_admin_read_allows_120_calls_per_minute(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(\App\Models\Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrateur'])->id);

        for ($i = 0; $i < 120; $i++) {
            $resp = $this->actingAs($admin)->getJson('/api/admin/users');
            $this->assertNotSame(429, $resp->status(), "request {$i} should not be throttled");
        }

        $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertStatus(429);
    }

    public function test_admin_write_blocks_after_60_destructive_calls_per_hour(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(\App\Models\Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrateur'])->id);

        // We point at a non-existent training id, so each call short-circuits
        // on findOrFail (404) — but the throttle still counts.
        for ($i = 0; $i < 60; $i++) {
            $resp = $this->actingAs($admin)->deleteJson('/api/admin/trainings/999999');
            $this->assertNotSame(429, $resp->status(), "request {$i} should not be throttled yet");
        }

        $this->actingAs($admin)
            ->deleteJson('/api/admin/trainings/999999')
            ->assertStatus(429);
    }

    public function test_buckets_are_keyed_by_user_so_two_users_have_independent_quotas(): void
    {
        $a = User::factory()->create(['kyc_level' => 'verified']);
        $b = User::factory()->create(['kyc_level' => 'verified']);

        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($a)->getJson('/api/v1/kyc/status')->assertStatus(200);
        }
        $this->actingAs($a)->getJson('/api/v1/kyc/status')->assertStatus(429);

        // User B starts fresh — must not be impacted.
        $this->actingAs($b)->getJson('/api/v1/kyc/status')->assertStatus(200);
    }
}
