<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Minuman', 'Makanan', 'Snack', 'Rokok', 'Sembako', 'Kebersihan', 'Alat Tulis', 'Obat']),
            'sort_order' => 0,
        ];
    }
}
