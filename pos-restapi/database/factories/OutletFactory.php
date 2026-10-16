<?php

namespace Database\Factories;

use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Outlet>
 */
class OutletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('???##')),
            'name' => 'Outlet '.fake()->city(),
            'address' => fake()->address(),
            'phone' => fake()->numerify('08##########'),
            'tax_rate' => 10,
            'service_charge_rate' => 0,
            'receipt_header' => null,
            'receipt_footer' => 'Terima kasih atas kunjungan Anda.',
        ];
    }
}
