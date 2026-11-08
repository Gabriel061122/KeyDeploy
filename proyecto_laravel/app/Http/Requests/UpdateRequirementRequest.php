<?php

namespace App\Http\Requests;

use App\Models\Requirement;
use Illuminate\Support\Facades\Gate;

/**
 * Edición de un requerimiento. Solo el propietario del Proyecto al que
 * pertenece puede modificarlo: la política comprueba la propiedad a
 * través de la relación del proyecto (RF17/RN09).
 */
class UpdateRequirementRequest extends RequirementFormRequest
{
    public function authorize(): bool
    {
        $requirement = $this->route('requirement');

        return $requirement instanceof Requirement
            && Gate::forUser($this->user())->allows('update', $requirement);
    }
}
