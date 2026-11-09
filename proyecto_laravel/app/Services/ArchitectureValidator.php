<?php

namespace App\Services;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Project;
use App\Models\Region;
use App\Models\Requirement;
use Illuminate\Support\Collection;

/**
 * Validación de dominio de las arquitecturas manuales (sección 7.1 UT2 / RN05 / RN08).
 *
 * Concentra TODAS las reglas que dependen de varias tablas o relacional
 * entre elementos: mínimo de elementos, variedad de tipos, productos
 * existentes, activos y disponibles en la región (RN05), coherencia de
 * cantidades y configuraciones JSON, región del proyecto y requisitos
 * obligatorios del proyecto. Los form requests y los controladores
 * delegan en este servicio en lugar de duplicar las reglas.
 */
final class ArchitectureValidator
{
    /** Mínimo de elementos de una arquitectura válida. */
    public const MIN_ITEMS = 3;

    /** Mínimo de tipos de producto distintos de una arquitectura válida. */
    public const MIN_DISTINCT_TYPES = 2;

    /**
     * Valida los elementos crudos del formulario contra una región y,
     * cuando se conoce el proyecto, contra su región y sus requisitos
     * obligatorios.
     *
     * Devuelve los elementos individualmente coherentes (identificador de
     * producto existente, cantidad entera >= 1 y configuración JSON
     * válida, decodificada) y la lista de errores de todo el conjunto.
     * Una arquitectura se considera válida solo cuando no existe ningún
     * error; el rechazo es siempre la operación íntegra.
     *
     * @param  array<int, array<string, mixed>>  $items  Filas tal y como llegan del formulario.
     * @return array{items: list<array{product_id: int, quantity: int, config: array<string, mixed>}>, errors: list<string>, valid: bool}
     */
    public function validate(Region $region, array $items, ?Project $project = null): array
    {
        $errors = [];
        $sanitized = [];
        $productIds = [];

        if ($project !== null && (int) $project->region_id !== (int) $region->id) {
            $errors[] = sprintf(
                'La región seleccionada "%s" no coincide con la región del proyecto "%s"; la arquitectura debe usar la región del proyecto.',
                $region->name,
                $project->region?->name ?? '—',
            );
        }

        foreach ($items as $index => $item) {
            $processable = $this->processRow($index, $item, $errors, $productIds);
            if ($processable !== null) {
                $sanitized[] = $processable;
            }
        }

        if (count($sanitized) < self::MIN_ITEMS) {
            $errors[] = sprintf(
                'La arquitectura necesita al menos %d elementos (recibidos: %d).',
                self::MIN_ITEMS,
                count($sanitized),
            );
        }

        $products = $this->resolveProducts($productIds);
        $problems = $this->checkProducts($items, $products, $region, $errors);
        $types = [];

        foreach ($sanitized as $element) {
            $product = $products->get($element['product_id']);
            if ($product !== null && ! in_array($element['product_id'], $problems, true)) {
                $types[] = $product->type instanceof ProductType ? $product->type->value : (string) $product->type;
            }
        }

        $distinctTypes = count(array_unique($types));
        if ($distinctTypes < self::MIN_DISTINCT_TYPES) {
            $errors[] = sprintf(
                'La arquitectura debe combinar al menos %d tipos de producto distintos (%d usados).',
                self::MIN_DISTINCT_TYPES,
                $distinctTypes,
            );
        }

        if ($project !== null) {
            $this->checkRequirements($project, $sanitized, $errors);
        }

        return [
            'items' => $sanitized,
            'errors' => $errors,
            'valid' => $errors === [],
        ];
    }

    /**
     * Comprueba los requisitos obligatorios del proyecto contra las
     * claves de primer nivel de la configuración de los elementos.
     *
     * Basta con que un valor de cualquier elemento que declare la clave
     * exacta satisfaga el operador; los requisitos opcionales no bloquean.
     *
     * @param  list<array{product_id: int, quantity: int, config: array<string, mixed>}>  $items
     * @param  list<string>  $errors
     */
    private function checkRequirements(Project $project, array $items, array &$errors): void
    {
        $requirements = $project->requirements()->orderBy('id')->get();
        if ($requirements->isEmpty()) {
            return;
        }

        $configs = array_map(
            static fn (array $item): array => $item['config'],
            $items,
        );

        foreach ($requirements as $requirement) {
            if (! $requirement->mandatory) {
                continue;
            }

            $key = (string) $requirement->field_key;
            $values = [];

            foreach ($configs as $config) {
                if (array_key_exists($key, $config)) {
                    $values[] = $config[$key];
                }
            }

            $condition = sprintf(
                '"%s" %s "%s"',
                $key,
                RequirementRules::OPERATOR_LABELS[$requirement->operator] ?? $requirement->operator,
                $requirement->value,
            );

            if ($values === []) {
                $errors[] = sprintf(
                    'No se satisface el requisito obligatorio %s: ningún elemento define la clave de configuración "%s".',
                    $condition,
                    $key,
                );

                continue;
            }

            foreach ($values as $value) {
                if ($this->satisfiesRequirement($requirement, $value)) {
                    continue 2;
                }
            }

            $errors[] = sprintf(
                'No se satisface el requisito obligatorio %s con la configuración de la arquitectura.',
                $condition,
            );
        }
    }

    private function satisfiesRequirement(Requirement $requirement, mixed $value): bool
    {
        $dataType = (string) $requirement->data_type;
        $operator = (string) $requirement->operator;

        if (! RequirementRules::isCompatible($dataType, $operator)) {
            return false;
        }

        $expected = RequirementRules::checkValue($dataType, (string) $requirement->value);
        if (! ($expected['valid'] ?? false)) {
            return false;
        }

        $actual = $this->normalizeConfigValue($dataType, $value);
        if ($actual === null) {
            return false;
        }

        return match ($dataType) {
            'string' => $this->compareStrings($operator, $actual, (string) $expected['normalized']),
            'integer', 'decimal' => $this->compareNumbers(
                $operator,
                (float) $actual,
                (float) $expected['normalized'],
            ),
            'boolean' => $operator === 'eq'
                ? $actual === $expected['normalized']
                : $actual !== $expected['normalized'],
            default => false,
        };
    }

    /**
     * Normaliza un valor de configuración con las mismas convenciones que
     * los requisitos del proyecto (coma decimal, booleanos equivalentes y
     * cadenas recortadas). Devuelve null si no es válido para el tipo.
     */
    private function normalizeConfigValue(string $dataType, mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        $string = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        $check = RequirementRules::checkValue($dataType, $string);

        return ($check['valid'] ?? false) ? $check['normalized'] : null;
    }

    private function compareStrings(string $operator, string $actual, string $expected): bool
    {
        return match ($operator) {
            'eq' => $actual === $expected,
            'neq' => $actual !== $expected,
            'contains' => str_contains($actual, $expected),
            'not_contains' => ! str_contains($actual, $expected),
            default => false,
        };
    }

    private function compareNumbers(string $operator, float $actual, float $expected): bool
    {
        return match ($operator) {
            'eq' => $actual == $expected,
            'neq' => $actual != $expected,
            'gt' => $actual > $expected,
            'gte' => $actual >= $expected,
            'lt' => $actual < $expected,
            'lte' => $actual <= $expected,
            default => false,
        };
    }

    /**
     * Comprueba la fila $index y agrega los errores concretos del campo.
     *
     * @param  list<string>  $errors
     * @param  list<int>  $productIds
     * @return array{product_id: int, quantity: int, config: array<string, mixed>}|null
     */
    private function processRow(int $index, array $item, array &$errors, array &$productIds): ?array
    {
        $position = $index + 1;

        $quantity = $item['quantity'] ?? null;
        if ($quantity === null || trim((string) $quantity) === '' || ! ctype_digit(trim((string) $quantity))) {
            $errors[] = sprintf('El elemento %d necesita una cantidad entera mayor o igual que 1.', $position);

            return null;
        }

        $quantityInt = (int) $quantity;
        if ($quantityInt < 1) {
            $errors[] = sprintf('El elemento %d necesita una cantidad entera mayor o igual que 1.', $position);

            return null;
        }

        $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
        if ($productId < 1) {
            $errors[] = sprintf('El elemento %d no tiene un producto seleccionado.', $position);

            return null;
        }

        $decodedConfig = $this->decodeConfig($item['config'] ?? null, $position, $errors);
        if ($decodedConfig === null) {
            return null;
        }

        $productIds[] = $productId;

        return [
            'product_id' => $productId,
            'quantity' => $quantityInt,
            'config' => $decodedConfig,
        ];
    }

    /**
     * @param  list<string>  $errors
     * @return array<string, mixed>|null
     */
    private function decodeConfig(mixed $rawConfig, int $position, array &$errors): ?array
    {
        if (is_array($rawConfig)) {
            return $rawConfig;
        }

        $trimmed = trim((string) $rawConfig);
        if ($trimmed === '') {
            return [];
        }

        try {
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $errors[] = sprintf(
                'El elemento %d contiene una configuración JSON no válida.',
                $position,
            );

            return null;
        }

        if (! is_array($decoded)) {
            $errors[] = sprintf(
                'La configuración del elemento %d debe ser un objeto JSON (p. ej. {"ram": "4GB"}).',
                $position,
            );

            return null;
        }

        return $decoded;
    }

    /**
     * @param  list<int>  $productIds
     * @return Collection<int, Product>
     */
    private function resolveProducts(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        return Product::query()
            ->with(['regions', 'availabilities', 'provider'])
            ->whereIn('id', array_unique($productIds))
            ->get()
            ->keyBy('id');
    }

    /**
     * Comprueba existencia, estado activo y disponibilidad en la región
     * (RN05: no se contrata un producto no disponible en la región) usando
     * la relación `availabilities` cargada.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, Product>  $products
     * @param  list<string>  $errors
     * @return list<int>
     */
    private function checkProducts(array $items, Collection $products, Region $region, array &$errors): array
    {
        $broken = [];

        foreach ($items as $index => $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            if ($productId < 1) {
                continue;
            }

            $position = $index + 1;
            $product = $products->get($productId);

            if ($product === null) {
                $errors[] = sprintf(
                    'El elemento %d usa el producto %d, que no existe en el catálogo.',
                    $position,
                    $productId,
                );
                $broken[] = $productId;

                continue;
            }

            if (! $product->active) {
                $errors[] = sprintf(
                    'El elemento %d usa "%s", que está retirado o inactivo y no puede contratarse.',
                    $position,
                    $product->name,
                );
                $broken[] = $productId;

                continue;
            }

            // Comprobar disponibilidad activa en la región en product_availabilities
            $availability = $product->availabilities->firstWhere('region_id', $region->id);
            $available = $availability !== null && $availability->available;

            if (! $available) {
                $errors[] = sprintf(
                    'El elemento %d usa "%s", que no está disponible en la región "%s".',
                    $position,
                    $product->name,
                    $region->name,
                );
                $broken[] = $productId;
            }
        }

        return array_values(array_unique($broken));
    }
}
