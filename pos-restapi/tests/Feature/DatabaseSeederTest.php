<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Outlet;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_outlet_owner_cashier_and_settings(): void
    {
        $this->seed();

        $outlet = Outlet::query()->where('code', 'JKT01')->firstOrFail();

        $owner = User::query()->where('email', 'owner@pos.test')->firstOrFail();
        $this->assertSame(Role::Owner, $owner->role);
        $this->assertTrue(Hash::check('password', $owner->password));

        $cashier = User::query()->where('role', Role::Cashier)->firstOrFail();
        $this->assertTrue(Hash::check('123456', $cashier->pin));
        $this->assertTrue($cashier->canAccessOutlet($outlet->id));

        $this->assertFalse(Setting::get('tax.inclusive'));
        $this->assertSame(5, Setting::get('pin.max_attempts'));
    }

    public function test_seeder_is_idempotent_and_keeps_changed_settings(): void
    {
        $this->seed();
        Setting::put('business.name', 'Kopi Rina');

        $this->seed();

        $this->assertSame(1, Outlet::query()->count());
        $this->assertSame(2, User::query()->count());
        $this->assertSame('Kopi Rina', Setting::get('business.name'));
    }

    public function test_pin_and_password_are_never_serialized(): void
    {
        $user = User::factory()->cashier()->create();

        $this->assertArrayNotHasKey('pin', $user->toArray());
        $this->assertArrayNotHasKey('password', $user->toArray());
    }
}
