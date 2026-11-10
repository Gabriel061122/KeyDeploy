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
        $type = fake()->randomElement(ProductType::cases());

        return [
            'provider_id' => Provider::factory(),
            'name' => 'Instancia '.fake()->words(2, true),
            'description' => fake()->sentence(),
            'type' => $type,
            'billing_unit' => 'mes',
            'config' => match ($type) {
                ProductType::Computacion, ProductType::ComputacionIa => ['vcpu' => 2, 'ram_gb' => 4],
                ProductType::Almacenamiento => ['capacity_gb' => 100, 'storage_type' => 'ssd'],
                ProductType::BasesDeDatos, ProductType::BaseDatos => ['engine' => 'postgresql', 'storage_gb' => 50],
                ProductType::Redes, ProductType::Red => ['bandwidth_mbps' => 100],
                ProductType::Seguridad => ['rules_count' => 10],
                ProductType::Backup => ['retention_days' => 30],
                default => [],
            },
            'active' => true,
        ];
    }
}
