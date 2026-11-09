<?php

namespace Database\Factories;

use App\Models\Architecture;
use App\Models\ArchitectureItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArchitectureItem>
 */
class ArchitectureItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'architecture_id' => Architecture::factory(),
            'product_id' => Product::factory(),
            'quantity' => 1,
            'config' => ['instancia' => 't3.medium'],
        ];
    }
}
