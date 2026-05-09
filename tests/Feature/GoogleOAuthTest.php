<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * 2026-05-09 — Google OAuth via Socialite.
 *
 * The redirect step is exercised through the real driver (we just check the
 * 302 to accounts.google.com). The callback step swaps the driver out via a
 * Mockery facade so the test never hits the real OAuth flow.
 */
class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.client_id'     => 'fake-client-id',
            'services.google.client_secret' => 'fake-client-secret',
            'services.google.redirect'      => 'http://localhost/auth/google/callback',
        ]);
        Role::firstOrCreate(['slug' => 'investor'], ['name' => 'Investisseur']);
    }

    protected function fakeGoogleUser(string $email, string $googleId, ?string $name = null, ?string $avatar = null): \Mockery\MockInterface
    {
        $mock = Mockery::mock('Laravel\\Socialite\\Two\\User');
        $mock->shouldReceive('getId')->andReturn($googleId);
        $mock->shouldReceive('getEmail')->andReturn($email);
        $mock->shouldReceive('getName')->andReturn($name ?? 'Test User');
        $mock->shouldReceive('getAvatar')->andReturn($avatar ?? 'https://lh3.googleusercontent.com/a/x.png');
        return $mock;
    }

    public function test_redirect_route_returns_302_to_google(): void
    {
        $resp = $this->get('/auth/google/redirect');
        $resp->assertStatus(302);
        $this->assertStringContainsString('accounts.google.com', $resp->headers->get('Location'));
    }

    public function test_callback_creates_new_user_with_default_investor_role(): void
    {
        Socialite::shouldReceive('driver->user')
            ->andReturn($this->fakeGoogleUser('newcomer@example.com', 'goog-001', 'Tamane Eric'));

        $resp = $this->get('/auth/google/callback');
        $resp->assertRedirect('/dashboard?oauth=google');

        $user = User::where('email', 'newcomer@example.com')->firstOrFail();
        $this->assertSame('goog-001', $user->google_id);
        $this->assertSame('google',   $user->oauth_provider);
        $this->assertSame('Tamane Eric', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('investor', $user->active_role_slug);
        $this->assertTrue($user->roles()->where('slug', 'investor')->exists());
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_links_existing_password_user_by_email(): void
    {
        $existing = User::factory()->create([
            'email'    => 'aminata@africaplus.test',
            'password' => Hash::make('legacy-pwd'),
        ]);

        Socialite::shouldReceive('driver->user')
            ->andReturn($this->fakeGoogleUser('aminata@africaplus.test', 'goog-002', 'Aminata Diop'));

        $resp = $this->get('/auth/google/callback');
        $resp->assertRedirect('/dashboard?oauth=google');

        $existing->refresh();
        $this->assertSame('goog-002', $existing->google_id);
        $this->assertSame('google',   $existing->oauth_provider);
        $this->assertNotNull($existing->email_verified_at);
        $this->assertAuthenticatedAs($existing);

        // No duplicate row created.
        $this->assertSame(1, User::where('email', 'aminata@africaplus.test')->count());
    }

    public function test_callback_logs_in_already_linked_user_without_modifying_anything_else(): void
    {
        $existing = User::factory()->create([
            'email'          => 'oksam@africaplus.test',
            'google_id'      => 'goog-003',
            'oauth_provider' => 'google',
            'name'           => 'Original Name',
        ]);

        Socialite::shouldReceive('driver->user')
            ->andReturn($this->fakeGoogleUser('oksam@africaplus.test', 'goog-003', 'Different Name From Google'));

        $resp = $this->get('/auth/google/callback');
        $resp->assertRedirect('/dashboard?oauth=google');

        $existing->refresh();
        // We don't overwrite the local name on every login — keep it stable.
        $this->assertSame('Original Name', $existing->name);
        $this->assertAuthenticatedAs($existing);
    }

    public function test_callback_redirects_to_login_with_failed_flag_on_socialite_error(): void
    {
        Socialite::shouldReceive('driver->user')
            ->andThrow(new \RuntimeException('OAuth state mismatch'));

        $this->get('/auth/google/callback')->assertRedirect('/connexion?oauth=failed');
    }

    public function test_callback_rejects_when_google_returns_no_email(): void
    {
        $mock = Mockery::mock('Laravel\\Socialite\\Two\\User');
        $mock->shouldReceive('getId')->andReturn('goog-004');
        $mock->shouldReceive('getEmail')->andReturn(null);

        Socialite::shouldReceive('driver->user')->andReturn($mock);

        $this->get('/auth/google/callback')->assertRedirect('/connexion?oauth=missing_profile_data');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
