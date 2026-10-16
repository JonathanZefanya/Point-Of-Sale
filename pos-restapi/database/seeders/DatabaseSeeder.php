<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SettingSeeder::class);

        $outlet = Outlet::query()->updateOrCreate(['code' => 'JKT01'], [
            'name' => 'Toko Pusat Jakarta',
            'address' => 'Jl. Sudirman No. 1, Jakarta',
            'phone' => '021-5550001',
            'tax_rate' => 10,
            'service_charge_rate' => 0,
            'receipt_header' => "Toko Pusat Jakarta\nJl. Sudirman No. 1, Jakarta",
            'receipt_footer' => 'Terima kasih atas kunjungan Anda.',
        ]);

        // Login back office: owner@pos.test / password
        $owner = User::query()->updateOrCreate(['email' => 'owner@pos.test'], [
            'name' => 'Pemilik Toko',
            'password' => 'password',
            'pin' => '654321',
            'role' => Role::Owner,
            'is_active' => true,
        ]);

        // Login kasir: pilih "Rina" lalu PIN 123456
        $cashier = User::query()->updateOrCreate(['email' => 'rina@pos.test'], [
            'name' => 'Rina',
            'password' => 'password',
            'pin' => '123456',
            'role' => Role::Cashier,
            'is_active' => true,
        ]);

        $owner->outlets()->syncWithoutDetaching([$outlet->id]);
        $cashier->outlets()->syncWithoutDetaching([$outlet->id]);
    }
}
