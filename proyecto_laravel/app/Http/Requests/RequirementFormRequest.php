<?php

namespace App\Http\Requests;

use App\Services\RequirementRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Reglas comunes de creación y edición de requerimientos (RF03):
 * compatibilidad tipo de dato ↔ operador y formato del valor según el
 * tipo, siempre en el servidor a partir de RequirementRules.
 */
abstract class RequirementFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'field_key' => ['required', 'string', 'max:100'],
            'data_type' => ['required', 'string', Rule::in(RequirementRules::TYPES)],
            'operator' => ['required', 'string'],
            'value' => ['required', 'string', 'max:500'],
            'mandatory' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Normaliza el valor antes de validar: decimales con coma → punto y
     * equivalentes de booleano → '1'/'0', para que la validación y el
     * almacenamiento usen una forma canónica.
     */
    protected function prepareForValidation(): void
    {
        $dataType = $this->input('data_type');
        $value = $this->input('value');

        if (! is_string($dataType) || ! in_array($dataType, RequirementRules::TYPES, true)) {
            return;
        }

        if (is_string($value)) {
            $this->merge(['value' => RequirementRules::checkValue($dataType, $value)['normalized'] ?? $value]);
        }
    }

    /**
     * Valida las combinaciones type↔operator y el formato del valor.
     * El formulario dinámico solo orienta al usuario: aquí se vuelve a
     * comprobar todo en el servidor, sin confiar en las opciones de Blade.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $dataType = (string) $this->input('data_type');
            $operator = (string) $this->input('operator');
            $value = (string) $this->input('value');

            if (! in_array($dataType, RequirementRules::TYPES, true)) {
                return;
            }

            if ($operator !== '' && ! RequirementRules::isCompatible($dataType, $operator)) {
                $validator->errors()->add('operator', RequirementRules::operatorError($dataType, $operator));
            }

            $check = RequirementRules::checkValue($dataType, $value);
            if ($value !== '' && ! $check['valid']) {
                $validator->errors()->add('value', self::valueError($dataType));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'field_key.required' => 'El campo del requerimiento es obligatorio.',
            'field_key.max' => 'El campo no puede exceder :max caracteres.',
            'data_type.required' => 'El tipo de dato es obligatorio.',
            'data_type.in' => 'El tipo de dato seleccionado no es válido.',
            'operator.required' => 'El operador es obligatorio.',
            'value.required' => 'El valor del requerimiento es obligatorio.',
            'value.max' => 'El valor no puede exceder :max caracteres.',
        ];
    }

    private static function valueError(string $dataType): string
    {
        return match ($dataType) {
            'integer' => 'El valor debe ser un número entero.',
            'decimal' => 'El valor debe ser un número decimal.',
            'boolean' => 'El valor debe ser verdadero o falso.',
            default => 'El valor no es válido para el tipo seleccionado.',
        };
    }
}
