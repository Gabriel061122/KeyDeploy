<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Operaciones de aplicación para los requerimientos manuales de proyectos.
 */
final class RequirementService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Project $project, array $attributes, bool $mandatory): Requirement
    {
        $attributes = $this->validatedAttributes($attributes);
        $attributes['mandatory'] = $mandatory;

        $requirement = new Requirement($attributes);
        $requirement->project()->associate($project);
        $requirement->source = Requirement::SOURCE_MANUAL;
        $requirement->save();

        return $requirement;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        Project $project,
        Requirement $requirement,
        array $attributes,
        bool $mandatory,
    ): void {
        $this->ensureRequirementBelongsToProject($project, $requirement);

        $attributes = $this->validatedAttributes($attributes);
        $attributes['mandatory'] = $mandatory;
        $requirement->update($attributes);
    }

    public function delete(Project $project, Requirement $requirement): void
    {
        $this->ensureRequirementBelongsToProject($project, $requirement);
        $requirement->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function validatedAttributes(array $attributes): array
    {
        $dataType = (string) ($attributes['data_type'] ?? '');
        if (! in_array($dataType, RequirementRules::TYPES, true)) {
            throw ValidationException::withMessages([
                'data_type' => 'El tipo de dato seleccionado no es válido.',
            ]);
        }

        $operator = (string) ($attributes['operator'] ?? '');
        if (! RequirementRules::isCompatible($dataType, $operator)) {
            throw ValidationException::withMessages([
                'operator' => RequirementRules::operatorError($dataType, $operator),
            ]);
        }

        $value = (string) ($attributes['value'] ?? '');
        $checkedValue = RequirementRules::checkValue($dataType, $value);
        if (! $checkedValue['valid']) {
            throw ValidationException::withMessages([
                'value' => RequirementRules::valueError($dataType),
            ]);
        }

        $attributes['value'] = $checkedValue['normalized'] ?? $value;

        return $attributes;
    }

    private function ensureRequirementBelongsToProject(Project $project, Requirement $requirement): void
    {
        if ((int) $requirement->project_id !== (int) $project->getKey()) {
            throw (new ModelNotFoundException())->setModel(Requirement::class, [$requirement->getKey()]);
        }
    }
}
