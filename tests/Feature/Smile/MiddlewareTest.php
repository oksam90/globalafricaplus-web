<?php

namespace Tests\Feature\Smile;

use App\Http\Middleware\RequireKYCLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Sprint 4 — T-15: RequireKYCLevel must:
 *   1. 401 unauthenticated requests
 *   2. 403 with `kyc_insufficient` when tier is below required
 *   3. 403 with `kyc_expired` when expires_at < now() (and downgrade the user)
 *   4. 403 with `aml_blocked` when the user is AML-blocked
 *   5. Pass through with a fresh, sufficient tier
 */
class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected RequireKYCLevel $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RequireKYCLevel();
    }

    protected function runMiddleware(?User $user, string $required = 'verified'): array
    {
        $request = Request::create('/test', 'GET');
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }
        $response = $this->middleware->handle($request, fn ($r) => response('OK', 200), $required);
        return [
            'status' => $response->getStatusCode(),
            'body'   => json_decode($response->getContent(), true) ?? [],
        ];
    }

    public function test_returns_401_for_unauthenticated_request(): void
    {
        ['status' => $status, 'body' => $body] = $this->runMiddleware(null);
        $this->assertSame(401, $status);
        $this->assertSame('auth_required', $body['error']);
    }

    public function test_returns_403_kyc_insufficient_for_basic_tier(): void
    {
        $user = User::factory()->create(['kyc_level' => 'basic']);
        ['status' => $status, 'body' => $body] = $this->runMiddleware($user, 'verified');

        $this->assertSame(403, $status);
        $this->assertSame('kyc_insufficient', $body['error']);
        $this->assertSame('verified', $body['required_level']);
    }

    public function test_returns_403_kyc_expired_and_downgrades_user(): void
    {
        $user = User::factory()->create([
            'kyc_level'      => 'verified',
            'kyc_expires_at' => now()->subDay(),
        ]);

        ['status' => $status, 'body' => $body] = $this->runMiddleware($user, 'verified');

        $this->assertSame(403, $status);
        $this->assertSame('kyc_expired', $body['error']);

        $user->refresh();
        $this->assertSame('basic', $user->kyc_level, 'expired user should be downgraded');
    }

    public function test_returns_403_aml_blocked_even_with_certified_tier(): void
    {
        $user = User::factory()->create([
            'kyc_level'      => 'certified',
            'kyc_expires_at' => now()->addYear(),
            'aml_status'     => 'blocked',
        ]);

        ['status' => $status, 'body' => $body] = $this->runMiddleware($user, 'verified');

        $this->assertSame(403, $status);
        $this->assertSame('aml_blocked', $body['error']);
    }

    public function test_passes_through_for_sufficient_fresh_tier(): void
    {
        $user = User::factory()->create([
            'kyc_level'      => 'verified',
            'kyc_expires_at' => now()->addYear(),
            'aml_status'     => 'clear',
        ]);

        ['status' => $status] = $this->runMiddleware($user, 'verified');
        $this->assertSame(200, $status);
    }
}
