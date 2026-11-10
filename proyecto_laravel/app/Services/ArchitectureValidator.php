<?php

namespace App\Services;

use App\Enums\PricingModel;
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
 * cantidades, consumo mensual y construcción de la configuración interna,
 * región del proyecto y requisitos obligatorios del proyecto. Los form
 * requests y los controladores delegan en este servicio en lugar de
 * duplicar las reglas.
 *
 * El cliente nunca aporta JSON: el consumo se recibe como un único valor
 * (`usage`) y aquí se traduce a `hours_per_month` o `gb_per_month` según
 * la modalidad de la tarifa. Los requisitos obligatorios se comprueban
 * contra los atributos del producto del catálogo (`products.config`).
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
     * producto existente, cantidad entera >= 1 y configuración interna
     * construida en el servidor) y la lista de errores de todo el
     * conjunto. Una arquitectura se considera válida solo cuando no existe
     * ningún error; el rechazo es siempre la operación íntegra.
     *
     * @param  array<int, array<string, mixed>>  $items  Filas tal y como llegan del formulario.
     * @return array{items: list<array{product_id: int, quantity: int, config: array<string, mixed>}>, errors: list<string>, valid: bool}
     */
    public function validate(Region $region, array $items, ?Project $project = null): array
    {
        $errors = [];

        if ($project !== null && (int) $project->region_id !== (int) $region->id) {
            $errors[] = sprintf(
                'La región seleccionada "%s" no coincide con la región del proyecto "%s"; la arquitectura debe usar la región del proyecto.',
                $region->name,
                $project->region?->name ?? '—',
            );
        }

        if (! Product::query()->availableIn($region)->exists()) {
            $errors[] = sprintf(
                'La región "%s" no tiene productos activos y disponibles; elige otra región.',
                $region->name,
            );
        }

        $sanitized = [];
        $productIds = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                $errors[] = sprintf('El elemento %d no tiene un formato válido.', $index + 1);

                continue;
            }

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
        $configured = [];

        foreach ($sanitized as $element) {
            $product = $products->get($element['product_id']);
            $config = $product !== null
                ? $this->buildConfig($region, $product, $element, $element['position'], $errors)
                : [];

            if ($product !== null && ! in_array($element['product_id'], $problems, true)) {
                $types[] = $product->type instanceof ProductType ? $product->type->value : (string) $product->type;
            }

            $configured[] = [
                'product_id' => $element['product_id'],
                'quantity' => $element['quantity'],
                'config' => $config,
            ];
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
            $this->checkRequirements($project, $configured, $products, $errors);
        }

        return [
            'items' => $configured,
            'errors' => $errors,
            'valid' => $errors === [],
        ];
    }

    /**
     * Comprueba los requisitos obligatorios del proyecto contra los
     * atributos del catálogo (`products.config`) de los productos
     * seleccionados.
     *
     * Basta con que un valor de cualquier producto declarado en la
     * arquitectura que declare la clave exacta satisfaga el operador; los
     * requisitos opcionales no bloquean. La cantidad del elemento no
     * altera el valor del atributo.
     *
     * @param  list<array{product_id: int, quantity: int, config: array<string, mixed>}>  $items
     * @param  Collection<int, Product>  $products
     * @param  list<string>  $errors
     */
    private function checkRequirements(Project $project, array $items, Collection $products, array &$errors): void
    {
        $requirements = $project->requirements()->orderBy('id')->get();
        if ($requirements->isEmpty()) {
            return;
        }

        $attributes = [];

        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            $config = $product !== null && is_array($product->config) ? $product->config : [];

            foreach ($config as $key => $value) {
                $attributes[(string) $key][] = $value;
            }
        }

        foreach ($requirements as $requirement) {
            if (! $requirement->mandatory) {
                continue;
            }

            $key = (string) $requirement->field_key;
            $values = $attributes[$key] ?? [];

            $condition = sprintf(
                '"%s" %s "%s"',
                $key,
                RequirementRules::OPERATOR_LABELS[$requirement->operator] ?? $requirement->operator,
                $requirement->value,
            );

            if ($values === []) {
                $errors[] = sprintf(
                    'No se satisface el requisito obligatorio %s: ningún producto seleccionado declara el atributo "%s" del catálogo.',
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
                'No se satisface el requisito obligatorio %s: los productos seleccionados declaran "%s", pero ninguno cumple el valor exigido.',
                $condition,
                $key,
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
     * Normaliza un valor de atributo del catálogo con las mismas
     * convenciones que los requisitos del proyecto (coma decimal,
     * booleanos equivalentes y cadenas recortadas). Devuelve null si no
     * es válido para el tipo.
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
     * @return array{product_id: int, quantity: int, usage: mixed, config: array<string, mixed>, position: int}|null
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

        $productIds[] = $productId;

        return [
            'product_id' => $productId,
            'quantity' => $quantityInt,
            'usage' => $item['usage'] ?? null,
            'config' => is_array($item['config'] ?? null) ? $item['config'] : [],
            'position' => $position,
        ];
    }

    /**
     * Construye en el servidor la configuración interna del elemento a
     * partir del consumo mensual y de la modalidad de la tarifa regional.
     * Las tarifas fijas se guardan sin configuración y los consumos de
     * tarifas por hora o por GB son obligatorios (> 0).
     *
     * @param  array{product_id: int, quantity: int, usage: mixed, config: array<string, mixed>, position: int}  $element
     * @param  list<string>  $errors
     * @return array<string, mixed>
     */
    private function buildConfig(Region $region, Product $product, array $element, int $position, array &$errors): array
    {
        $availability = $product->availabilities->firstWhere('region_id', $region->id);

        if ($availability === null) {
            return [];
        }

        $pricingModel = PricingModel::tryFrom((string) $availability->getRawOriginal('pricing_model'));

        if ($pricingModel === null || $pricingModel === PricingModel::FijoMensual) {
            return [];
        }

        $field = $pricingModel === PricingModel::PorHora
            ? ArchitecturePricingService::HOURS_FIELD
            : ArchitecturePricingService::GB_FIELD;

        $rawUsage = $element['usage'] ?? null;

        if ($rawUsage === null && array_key_exists($field, $element['config'])) {
            $rawUsage = $element['config'][$field];
        }

        $usage = $this->normalizeUsage($rawUsage);

        if ($usage === null || $usage <= 0) {
            $errors[] = $this->usageRequiredMessage($position, $product->name, $pricingModel);

            return [];
        }

        return [$field => $usage];
    }

    private function usageRequiredMessage(int $position, string $productName, PricingModel $pricingModel): string
    {
        return match ($pricingModel) {
            PricingModel::PorHora => sprintf(
                'El elemento %d ("%s") se factura por hora: indica el consumo mensual en horas.',
                $position,
                $productName,
            ),
            PricingModel::PorGb => sprintf(
                'El elemento %d ("%s") se factura por GB: indica el consumo mensual en GB.',
                $position,
                $productName,
            ),
            default => sprintf(
                'El elemento %d ("%s") necesita un consumo mensual válido.',
                $position,
                $productName,
            ),
        };
    }

    /**
     * Normaliza el consumo a un número JSON no negativo con hasta dos
     * decimales. Devuelve null si no es válido.
     */
    private function normalizeUsage(mixed $value): int|float|null
    {
        if (is_bool($value) || is_array($value) || is_object($value)) {
            return null;
        }

        $normalized = is_string($value) ? trim($value) : (string) $value;

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized)) {
            return null;
        }

        $number = (float) $normalized;

        if ($number > 999999999.99) {
            return null;
        }

        return $number == floor($number) ? (int) $number : $number;
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
