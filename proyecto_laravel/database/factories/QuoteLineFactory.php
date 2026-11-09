<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLine>
 */
class QuoteLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pricingModel = $this->faker->randomElement(['mensual', 'unico', 'por_uso']);
        $unitPrice = $this->faker->numberBetween(500, 50000);
        $quantity = $this->faker->numberBetween(1, 5);

        return [
            'quote_id' => Quote::factory(),
            'product_name' => $this->faker->words(3, true),
            'product_type' => $this->faker->randomElement(['computacion', 'almacenamiento', 'base_datos', 'red', 'seguridad']),
            'pricing_model' => $pricingModel,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPrice,
            'line_total_cents' => $unitPrice * $quantity,
            'config' => ['region' => 'eu-west-1'],
        ];
    }
}
