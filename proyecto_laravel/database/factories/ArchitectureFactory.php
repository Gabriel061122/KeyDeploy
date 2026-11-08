<?php

namespace Database\Factories;

use App\Models\Architecture;
use App\Models\Project;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Architecture>
 */
class ArchitectureFactory extends Factory
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
            'region_id' => Region::factory(),
            'name' => 'Arquitectura '.$this->faker->words(2, true),
            'source' => Architecture::SOURCE_MANUAL,
        ];
    }
}
