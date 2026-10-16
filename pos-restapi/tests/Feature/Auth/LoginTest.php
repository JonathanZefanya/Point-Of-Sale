<?php

namespace Tests\Feature\Auth;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_login_with_email_and_password(): void
    {
        $outlet = Outlet::factory()->create(['code' => 'JKT01']);
        $owner = User::factory()->owner()->create(['email' => 'owner@pos.test']);
        $owner->outlets()->attach($outlet);

        $this->postJson('/api/v1/auth/login', ['email' => 'owner@pos.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'owner')
            ->assertJsonPath('data.user.role_label', 'Pemilik')
            ->assertJsonPath('data.user.outlets.0.code', 'JKT01')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.pin')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'has_pin']]]);

        $this->assertSame(1, $owner->tokens()->count());
    }

    public function test_wrong_password_returns_field_error(): void
    {
        User::factory()->create(['email' => 'owner@pos.test']);

        $this->postJson('/api/v1/auth/login', ['email' => 'owner@pos.test', 'password' => 'salah'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Data tidak valid.')
            ->assertJsonPath('errors.email.0', 'Email atau password salah. Periksa kembali lalu coba lagi.');
    }

    public function test_unknown_email_gets_same_message_as_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'tidak-ada@pos.test', 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Email atau password salah. Periksa kembali lalu coba lagi.');
    }

    public function test_missing_fields_return_indonesian_validation(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Email wajib diisi.')
            ->assertJsonPath('errors.password.0', 'Password wajib diisi.');
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'nonaktif@pos.test']);

        $this->postJson('/api/v1/auth/login', ['email' => 'nonaktif@pos.test', 'password' => 'password'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Akun Anda dinonaktifkan. Hubungi pemilik toko untuk mengaktifkan kembali.');
    }

    public function test_login_is_throttled_after_five_wrong_attempts(): void
    {
        User::factory()->create(['email' => 'owner@pos.test']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'owner@pos.test', 'password' => 'salah'])->assertStatus(422);
        }

        // Password benar pun ditolak selama masa tunggu
        $this->postJson('/api/v1/auth/login', ['email' => 'owner@pos.test', 'password' => 'password'])
            ->assertStatus(429)
            ->assertJsonPath('message', fn (string $m) => str_starts_with($m, 'Terlalu banyak percobaan login. Coba lagi dalam'));

        $this->travel(61)->seconds();

        $this->postJson('/api/v1/auth/login', ['email' => 'owner@pos.test', 'password' => 'password'])->assertOk();
    }
}
