<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $cost = fake()->numberBetween(2, 40) * 500;

        return [
            'category_id' => null,
            'sku' => strtoupper(fake()->unique()->bothify('TST-####')),
            'barcode' => fake()->unique()->ean13(),
            'name' => ucwords(fake()->unique()->words(2, true)),
            'type' => ProductType::Goods,
            'unit' => 'pcs',
            'cost_price' => $cost,
            'sell_price' => $cost + 2000,
            'track_stock' => true,
            'min_stock' => 5,
            'is_active' => true,
        ];
    }

    public function service(): static
    {
        return $this->state(fn () => ['type' => ProductType::Service, 'track_stock' => false, 'barcode' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
