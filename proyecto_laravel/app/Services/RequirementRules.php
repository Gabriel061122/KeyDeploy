<?php

namespace App\Services;

/**
 * Codificación y validación de requerimientos estructurados (RF03).
 *
 * Única fuente de verdad para las combinaciones válidas entre tipo de
 * dato y operador y para el formato del valor según el tipo. La usan
 * tanto los Form Requests como las vistas Blade (formulario dinámico),
 * de forma que las reglas de negocio nunca se dupliquen.
 */
final class RequirementRules
{
    public const TYPES = ['string', 'integer', 'decimal', 'boolean'];

    /**
     * Operadores válidos por tipo de dato. Las combinaciones no incluidas
     * aquí se rechazan en el servidor, aunque el formulario las ofrezca.
     *
     * @var array<string, list<string>>
     */
    public const OPERATORS_BY_TYPE = [
        'string' => ['eq', 'neq', 'contains', 'not_contains'],
        'integer' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte'],
        'decimal' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte'],
        'boolean' => ['eq', 'neq'],
    ];

    /**
     * Etiquetas legibles de los operadores para la interfaz.
     *
     * @var array<string, string>
     */
    public const OPERATOR_LABELS = [
        'eq' => 'igual a',
        'neq' => 'distinto de',
        'contains' => 'contiene',
        'not_contains' => 'no contiene',
        'gt' => 'mayor que',
        'gte' => 'mayor o igual que',
        'lt' => 'menor que',
        'lte' => 'menor o igual que',
    ];

    /**
     * Etiquetas legibles de los tipos de dato para la interfaz.
     *
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        'string' => 'Cadena de texto',
        'integer' => 'Número entero',
        'decimal' => 'Número decimal',
        'boolean' => 'Verdadero/Falso',
    ];

    /**
     * Operadores compatibles con un tipo de dato.
     *
     * @return list<string>
     */
    public static function operatorsFor(string $dataType): array
    {
        return self::OPERATORS_BY_TYPE[$dataType] ?? [];
    }

    /**
     * Indica si la combinación tipo de dato + operador está permitida.
     */
    public static function isCompatible(string $dataType, string $operator): bool
    {
        return in_array($operator, self::operatorsFor($dataType), true);
    }

    /**
     * Comprueba que el valor tenga el formato esperado por el tipo de dato:
     * `string` → texto; `integer` → número entero; `decimal` → número
     * decimal (acepta coma como separador y devuelve la forma con punto);
     * `boolean` → verdadero o falso.
     *
     * @return array{valid: bool, normalized?: string}
     */
    public static function checkValue(string $dataType, string $value): array
    {
        $trimmed = trim($value);

        $normalized = match ($dataType) {
            'string' => $trimmed,
            'integer' => $trimmed,
            'decimal' => str_replace(',', '.', $trimmed),
            'boolean' => self::normalizeBoolean($trimmed),
            default => null,
        };

        if ($normalized === null) {
            return ['valid' => false];
        }

        return [
            'valid' => self::hasValueFormat($dataType, $normalized),
            'normalized' => $normalized,
        ];
    }

    /**
     * Comprueba el formato de un valor ya normalizado.
     */
    public static function hasValueFormat(string $dataType, string $value): bool
    {
        return match ($dataType) {
            'string' => true,
            'integer' => (bool) preg_match('/^-?\d+$/', $value),
            'decimal' => (bool) preg_match('/^-?\d+(\.\d+)?$/', $value),
            'boolean' => in_array(strtolower($value), ['0', '1', 'true', 'false'], true),
            default => false,
        };
    }

    /**
     * Mensajes útiles para identificar qué combinación falló.
     */
    public static function operatorError(string $dataType, string $operator): string
    {
        return 'El operador "'.$operator.'" no admite el tipo "'.$dataType.'". '
            .'Operadores válidos: '.implode(', ', self::operatorsFor($dataType)).'.';
    }

    /**
     * Mensaje de validación para un valor que no respeta su tipo.
     */
    public static function valueError(string $dataType): string
    {
        return match ($dataType) {
            'integer' => 'El valor debe ser un número entero.',
            'decimal' => 'El valor debe ser un número decimal.',
            'boolean' => 'El valor debe ser verdadero o falso.',
            default => 'El valor no es válido para el tipo seleccionado.',
        };
    }

    private static function normalizeBoolean(string $value): ?string
    {
        return match (strtolower($value)) {
            'verdad', 'verdadero', 'true', '1', 'si', 'sí' => '1',
            'falso', 'false', '0', 'no' => '0',
            default => $value,
        };
    }
}
