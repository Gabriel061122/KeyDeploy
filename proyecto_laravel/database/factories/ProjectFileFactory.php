<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectFile>
 */
class ProjectFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = $this->faker->randomElement(['pdf', 'png', 'jpg', 'docx', 'md']);
        return [
            'project_id' => Project::factory(),
            'architecture_id' => null,
            'original_name' => $this->faker->words(3, true) . '.' . $extension,
            'storage_path' => 'project-files/' . uniqid() . '.' . $extension,
            'mime_type' => match ($extension) {
                'pdf' => 'application/pdf',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'md' => 'text/markdown',
            },
            'size_bytes' => $this->faker->numberBetween(1024, 10485760),
        ];
    }
}
