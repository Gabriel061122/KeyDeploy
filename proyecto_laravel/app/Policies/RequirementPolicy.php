<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Requirement;
use App\Models\User;

/**
 * Política de los requerimientos: comparten la propiedad del Proyecto
 * al que pertenecen (RF03/RF09/RN09).
 */
class RequirementPolicy
{
    public function view(User $user, Requirement $requirement): bool
    {
        return (int) $requirement->project->user_id === $user->id && ! $requirement->project->trashed();
    }

    /**
     * El Gate consume el nombre de clase (primer argumento del array)
     * para resolver la policy y entrega aquí el proyecto destino.
     *
     * `Gate::allows('create', [Requirement::class, $project])` →
     * create($user, $project).
     */
    public function create(User $user, Project $project): bool
    {
        return ! $project->trashed() && (int) $project->user_id === $user->id;
    }

    public function update(User $user, Requirement $requirement): bool
    {
        return (int) $requirement->project->user_id === $user->id && ! $requirement->project->trashed();
    }

    public function delete(User $user, Requirement $requirement): bool
    {
        return (int) $requirement->project->user_id === $user->id && ! $requirement->project->trashed();
    }
}
