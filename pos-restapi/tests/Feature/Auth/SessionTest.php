<?php

namespace Tests\Feature\Auth;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_me_returns_current_user_with_outlets(): void
    {
        $outlet = Outlet::factory()->create(['code' => 'JKT01']);
        $user = User::factory()->cashier()->create(['name' => 'Rina']);
        $user->outlets()->attach($outlet);

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Rina')
            ->assertJsonPath('data.has_pin', true)
            ->assertJsonPath('data.can_authorize', false)
            ->assertJsonPath('data.outlets.0.code', 'JKT01');
    }

    public function test_me_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);
        $otherDeviceToken = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);

        $this->app['auth']->forgetGuards();
        $this->withToken($otherDeviceToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_user_deactivated_mid_session_loses_access(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $user->update(['is_active' => false]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(403);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_token_expires_after_configured_minutes(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->travel(config('sanctum.expiration') + 1)->minutes();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
    }
}
