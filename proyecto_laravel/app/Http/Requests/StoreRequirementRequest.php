<?php

namespace App\Http\Requests;

use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Support\Facades\Gate;

/**
 * Creación de un requerimiento dentro de un Proyecto propio (RF03).
 * `source` y `project_id` no se aceptan del formulario: el controlador
 * impone `source = manual` y `project_id` desde la ruta anidada.
 */
class StoreRequirementRequest extends RequirementFormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            && Gate::forUser($this->user())->allows('create', [Requirement::class, $project]);
    }
}
