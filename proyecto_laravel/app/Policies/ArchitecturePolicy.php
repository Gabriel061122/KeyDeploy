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
     * Emitir un presupuesto desde una arquitectura manual exige rol
     * CLIENTE, propiedad efectiva del proyecto (no eliminado) y que la
     * arquitectura sea de procedencia manual.
     */
    public function emitQuote(User $user, Architecture $architecture): bool
    {
        if ($this->roleOf($user) !== User::ROLE_CLIENTE) {
            return false;
        }

        $project = $architecture->project()->withTrashed()->first();

        return $project !== null
            && ! $project->trashed()
            && (int) $project->user_id === (int) $user->id
            && $architecture->source === Architecture::SOURCE_MANUAL;
    }

    /**
     * El rol se toma de la BD cuando el modelo aún no lo trae cargado
     * (por ejemplo, tras crearse con valores por defecto de la tabla).
     */
    private function roleOf(User $user): ?string
    {
        if ($user->role !== null) {
            return $user->role;
        }

        return $user->exists
            ? $user->newQuery()->whereKey($user->getKey())->value('role')
            : null;
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
