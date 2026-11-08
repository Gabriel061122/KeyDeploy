<?php

namespace App\Policies;

use App\Models\Architecture;
use App\Models\Project;
use App\Models\User;

/**
 * Política de las arquitecturas: una arquitectura pertenece al proyecto
 * de un propietario; solo él puede crearla o consultarla (RF17/RN09).
 * Una arquitectura aceptada (RN12) queda congelada, así que este módulo
 * no ofrece edición posterior: los cambios se tramitan como propuesta
 * nueva desde el flujo del resto del equipo.
 */
class ArchitecturePolicy
{
    public function view(User $user, Architecture $architecture): bool
    {
        return (int) $architecture->project->user_id === $user->id && ! $architecture->project->trashed();
    }

    /**
     * El Gate consume el nombre de clase (primer argumento del array)
     * para resolver la policy y entrega aquí el proyecto destino:
     * `Gate::allows('create', [Architecture::class, $project])` →
     * create($user, $project).
     */
    public function create(User $user, Project $project): bool
    {
        return ! $project->trashed() && (int) $project->user_id === $user->id;
    }
}
