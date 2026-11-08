<?php

namespace Tests\Unit\Services;

use App\Services\RequirementRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Matriz tipo de dato ↔ operador ↔ valor (RF03) en la única fuente de
 * verdad del dominio: RequirementRules.
 */
class RequirementRulesTest extends TestCase
{
    public function test_operator_matrix_matches_documented_combinations(): void
    {
        $this->assertSame(
            ['eq', 'neq', 'contains', 'not_contains'],
            RequirementRules::operatorsFor('string'),
        );
        $this->assertSame(
            ['eq', 'neq', 'gt', 'gte', 'lt', 'lte'],
            RequirementRules::operatorsFor('integer'),
        );
        $this->assertSame(
            ['eq', 'neq', 'gt', 'gte', 'lt', 'lte'],
            RequirementRules::operatorsFor('decimal'),
        );
        $this->assertSame(['eq', 'neq'], RequirementRules::operatorsFor('boolean'));
        $this->assertSame([], RequirementRules::operatorsFor('texto_inventado'));
    }

    public static function compatibility_cases(): array
    {
        return [
            'string mas contains es compatibles' => ['string', 'contains', true],
            'string mas not_contains es compatibles' => ['string', 'not_contains', true],
            'string mas gt no es compatible' => ['string', 'gt', false],
            'string mas gte no es compatible' => ['string', 'gte', false],
            'integer mas gte es compatible' => ['integer', 'gte', true],
            'integer mas lt es compatible' => ['integer', 'lt', true],
            'integer mas contains no es compatible' => ['integer', 'contains', false],
            'decimal mas lt es compatible' => ['decimal', 'lt', true],
            'decimal mas gt es compatible' => ['decimal', 'gt', true],
            'decimal mas contains no es compatible' => ['decimal', 'contains', false],
            'boolean mas eq es compatible' => ['boolean', 'eq', true],
            'boolean mas neq es compatible' => ['boolean', 'neq', true],
            'boolean mas contains no es compatible' => ['boolean', 'contains', false],
            'boolean mas gt no es compatible' => ['boolean', 'gt', false],
        ];
    }

    #[DataProvider('compatibility_cases')]
    public function test_combinations_of_type_and_operator(string $type, string $operator, bool $expected): void
    {
        $this->assertSame($expected, RequirementRules::isCompatible($type, $operator), "[$type + $operator]");
    }

    public static function value_cases(): array
    {
        return [
            'string acepta texto' => ['string', 'PostgreSQL Linux', true, 'PostgreSQL Linux'],
            'integer acepta enteros' => ['integer', '42', true, '42'],
            'integer acepta negativos' => ['integer', '-7', true, '-7'],
            'integer rechaza decimales' => ['integer', '3.5', false, '3.5'],
            'integer rechaza texto' => ['integer', 'abc', false, 'abc'],
            'decimal acepta punto' => ['decimal', '3.25', true, '3.25'],
            'decimal normaliza coma' => ['decimal', '3,25', true, '3.25'],
            'decimal rechaza texto no numérico' => ['decimal', '12.5.5', false, '12.5.5'],
            'boolean acepta 1' => ['boolean', '1', true, '1'],
            'boolean acepta 0' => ['boolean', '0', true, '0'],
            'boolean acepta verdadero escrito' => ['boolean', 'verdadero', true, '1'],
            'boolean acepta falso escrito' => ['boolean', 'falso', true, '0'],
            'boolean rechaza texto arbitrario' => ['boolean', 'quizás', false, 'quizás'],
        ];
    }

    #[DataProvider('value_cases')]
    public function test_value_format_validation(string $type, string $value, bool $valid, string $normalized): void
    {
        $result = RequirementRules::checkValue($type, $value);

        $this->assertSame($valid, $result['valid'], "[$type] valor \"$value\"");
        $this->assertSame($normalized, $result['normalized'] ?? null);
    }
}
