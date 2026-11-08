<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $suffix = $this->unique()->numberBetween(100000, 999999);

        return [
            'code' => 'prd-'.$suffix,
            'name' => 'Producto '.$this->faker->word(),
            'type' => $this->faker->randomElement(Product::TYPES),
            'unit_price' => $this->faker->randomFloat(2, 5, 500),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
