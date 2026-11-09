<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'status' => 'emitido',
            'subtotal_monthly_cents' => 0,
            'subtotal_one_time_cents' => 0,
            'total_cents' => 0,
            'issued_at' => now(),
        ];
    }
}
