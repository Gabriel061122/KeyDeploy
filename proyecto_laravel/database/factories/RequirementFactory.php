<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requirement>
 */
class RequirementFactory extends Factory
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
            'field_key' => $this->faker->randomElement([
                'almacenamiento_gb', 'usuarios', 'nucleos_cpu', 'memoria_gb',
            ]),
            'operator' => 'gte',
            'value' => (string) $this->faker->numberBetween(10, 2000),
            'data_type' => 'integer',
            'mandatory' => $this->faker->boolean(),
            'source' => Requirement::SOURCE_MANUAL,
        ];
    }

    public function mandatory(): static
    {
        return $this->state(fn (array $attributes) => [
            'mandatory' => true,
        ]);
    }
}
