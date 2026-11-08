<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validación de la edición de un Proyecto (RF02). autorización vía
 * ProjectPolicy: solo el propietario puede modificar su proyecto.
 */
class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && Gate::forUser($this->user())->allows('update', $project);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->max_budget)) {
            $this->merge([
                'max_budget' => str_replace(',', '.', trim($this->max_budget)),
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'region_id' => ['required', 'integer', Rule::exists('regions', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'max_budget' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'expected_users' => ['required', 'integer', 'min:1', 'max:4294967295'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del proyecto es obligatorio.',
            'description.required' => 'La descripción funcional es obligatoria.',
            'region_id.required' => 'La región es obligatoria.',
            'region_id.exists' => 'La región seleccionada no existe en el catálogo.',
            'max_budget.required' => 'El presupuesto máximo es obligatorio.',
            'max_budget.min' => 'El presupuesto máximo no puede ser negativo.',
            'expected_users.required' => 'El número esperado de usuarios es obligatorio.',
            'expected_users.min' => 'El número esperado de usuarios debe ser al menos 1.',
        ];
    }
}
