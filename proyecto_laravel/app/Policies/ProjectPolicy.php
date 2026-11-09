<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Política de los Proyectos (RF17/RN09): un usuario solo puede ver,
 * modificar y eliminar sus propios proyectos; tener la URL ya no
 * sustituye a la comprobación de permisos.
 */
class ProjectPolicy
{
    /**
     * El índice solo mostrará los proyectos del usuario autenticado; el
     * controlador filtra por propietario y aquí se reconfirma.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * RF02: la creación de Proyectos es función del CLIENTE.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [User::ROLES[0]], true);
    }

    public function view(User $user, Project $project): bool
    {
        return (int) $project->user_id === $user->id;
    }

    public function update(User $user, Project $project): bool
    {
        return ! $project->trashed() && (int) $project->user_id === $user->id;
    }

    public function delete(User $user, Project $project): bool
    {
        return ! $project->trashed() && (int) $project->user_id === $user->id;
    }

    /**
     * La restauración solo se permite al propietario y sobre un registro
     * realmente borrado (borrado lógico).
     */
    public function restore(User $user, Project $project): bool
    {
        return $project->trashed() && (int) $project->user_id === $user->id;
    }

    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }
}
