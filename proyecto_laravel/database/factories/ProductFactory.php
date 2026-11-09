<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'name' => 'Instancia '.fake()->words(2, true),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(ProductType::cases()),
            'billing_unit' => 'mes',
            'config' => ['vcpu' => 2, 'ram_gb' => 4],
            'active' => true,
        ];
    }
}
