<?php

namespace Tests\Feature\Auth;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cashier = User::factory()->cashier('123456')->create(['name' => 'Rina']);
    }

    private function pinLogin(string $pin)
    {
        return $this->postJson('/api/v1/auth/pin-login', ['user_id' => $this->cashier->id, 'pin' => $pin]);
    }

    public function test_cashier_can_login_with_pin(): void
    {
        $this->pinLogin('123456')
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Rina')
            ->assertJsonPath('data.user.role', 'cashier')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertSame('cashier', $this->cashier->tokens()->first()->name);
    }

    public function test_pin_must_be_six_digits(): void
    {
        $this->pinLogin('12ab')
            ->assertStatus(422)
            ->assertJsonPath('errors.pin.0', 'PIN harus 6 digit angka.');
    }

    public function test_wrong_pin_shows_remaining_attempts(): void
    {
        $this->pinLogin('000000')
            ->assertStatus(422)
            ->assertJsonPath('errors.pin.0', 'PIN salah. Sisa 4 percobaan sebelum dikunci 5 menit.');

        $this->pinLogin('000000')
            ->assertJsonPath('errors.pin.0', 'PIN salah. Sisa 3 percobaan sebelum dikunci 5 menit.');
    }

    public function test_five_wrong_pins_lock_for_five_minutes(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->pinLogin('000000')->assertStatus(422);
        }

        $this->pinLogin('000000')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '300')
            ->assertJsonPath('retry_after', 300)
            ->assertJsonPath('message', 'PIN salah 5 kali. Login PIN dikunci. Coba lagi dalam 5 menit atau minta supervisor login.');

        // PIN benar tetap ditolak selama terkunci, dengan sisa waktu
        $this->travel(90)->seconds();
        $this->pinLogin('123456')
            ->assertStatus(429)
            ->assertJsonPath('retry_after', 210)
            ->assertJsonPath('message', 'Login PIN dikunci. Coba lagi dalam 3 menit 30 detik atau minta supervisor login.');

        $this->travel(211)->seconds();
        $this->pinLogin('123456')->assertOk();
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->pinLogin('000000');
        }
        $this->pinLogin('123456')->assertOk();

        $this->pinLogin('000000')
            ->assertJsonPath('errors.pin.0', 'PIN salah. Sisa 4 percobaan sebelum dikunci 5 menit.');
    }

    public function test_lockout_is_per_user(): void
    {
        $other = User::factory()->cashier('222222')->create();

        for ($i = 0; $i < 5; $i++) {
            $this->pinLogin('000000');
        }

        $this->postJson('/api/v1/auth/pin-login', ['user_id' => $other->id, 'pin' => '222222'])->assertOk();
    }

    public function test_user_without_pin_or_unknown_cannot_pin_login(): void
    {
        $noPin = User::factory()->create(['pin' => null]);

        $this->postJson('/api/v1/auth/pin-login', ['user_id' => $noPin->id, 'pin' => '123456'])
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Kasir tidak ditemukan atau belum memiliki PIN. Hubungi pemilik toko.');

        $this->postJson('/api/v1/auth/pin-login', ['user_id' => 9999, 'pin' => '123456'])->assertStatus(422);
    }

    public function test_inactive_cashier_cannot_pin_login(): void
    {
        $this->cashier->update(['is_active' => false]);

        $this->pinLogin('123456')->assertStatus(403);
    }

    public function test_pin_users_lists_active_cashiers_of_outlet(): void
    {
        $outlet = Outlet::factory()->create(['code' => 'JKT01']);
        $otherOutlet = Outlet::factory()->create(['code' => 'BDG01']);

        $budi = User::factory()->cashier()->create(['name' => 'Budi']);
        $inactive = User::factory()->cashier()->inactive()->create(['name' => 'Nonaktif']);
        $noPin = User::factory()->create(['name' => 'Tanpa PIN', 'pin' => null]);
        $elsewhere = User::factory()->cashier()->create(['name' => 'Bandung']);

        $outlet->users()->attach([$this->cashier->id, $budi->id, $inactive->id, $noPin->id]);
        $otherOutlet->users()->attach($elsewhere);

        $this->getJson('/api/v1/auth/pin-users?outlet_code=JKT01')
            ->assertOk()
            ->assertExactJson(['data' => [
                ['id' => $budi->id, 'name' => 'Budi'],
                ['id' => $this->cashier->id, 'name' => 'Rina'],
            ]]);
    }

    public function test_pin_users_requires_valid_outlet_code(): void
    {
        $this->getJson('/api/v1/auth/pin-users')
            ->assertStatus(422)
            ->assertJsonPath('errors.outlet_code.0', 'Kode outlet wajib diisi.');

        $this->getJson('/api/v1/auth/pin-users?outlet_code=XXX')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Outlet dengan kode XXX tidak ditemukan. Periksa pengaturan perangkat kasir.');
    }
}
