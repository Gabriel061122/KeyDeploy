<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;

/**
 * Operaciones de aplicación para el ciclo de vida de los proyectos.
 */
final class ProjectService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $owner, array $attributes): Project
    {
        $project = new Project($attributes);
        $project->user_id = $owner->getKey();
        $project->save();

        return $project;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Project $project, array $attributes): void
    {
        $project->update($attributes);
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    public function restore(Project $project): void
    {
        $project->restore();
    }
}
