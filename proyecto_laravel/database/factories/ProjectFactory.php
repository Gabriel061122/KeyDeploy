<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'region_id' => Region::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'max_budget' => $this->faker->randomFloat(2, 100, 10000),
            'expected_users' => $this->faker->numberBetween(1, 10000),
        ];
    }
}
