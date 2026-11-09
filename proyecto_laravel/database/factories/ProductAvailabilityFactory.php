<?php

namespace Database\Factories;

use App\Enums\PricingModel;
use App\Models\Product;
use App\Models\ProductAvailability;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductAvailability>
 */
class ProductAvailabilityFactory extends Factory
{
    protected $model = ProductAvailability::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'region_id' => Region::factory(),
            'available' => true,
            'price_cents' => fake()->numberBetween(500, 25000),
            'one_time_price_cents' => 0,
            'pricing_model' => fake()->randomElement(PricingModel::cases()),
        ];
    }
}
