<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Region;
use Illuminate\Support\Collection;

/**
 * Validación de dominio de las arquitecturas manuales.
 *
 * Concentra TODAS las reglas que dependen de varias tablas o relacional
 * entre elementos: mínimo de elementos, variedada de tipos, productos
 * existentes, activos y disponibles en la región (RN05) y coherencia de
 * cantidades y configuraciones JSON. Los form requests y los
 * controladores delegan en este servicio en lugar de duplicar las reglas.
 */
final class ArchitectureValidator
{
    /** Mínimo de elementos de una arquitectura válida. */
    public const MIN_ITEMS = 3;

    /** Mínimo de tipos de producto distintos de una arquitectura válida. */
    public const MIN_DISTINCT_TYPES = 2;

    /**
     * Valida los elementos crudos del formulario contra una región.
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
    public function validate(Region $region, array $items): array
    {
        $errors = [];
        $sanitized = [];
        $productIds = [];

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
                $types[] = $product->type;
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

        return [
            'items' => $sanitized,
            'errors' => $errors,
            'valid' => $errors === [],
        ];
    }

    /**
     * Comprueba la fila $index y agrega los errores concretos del campo.
     *
     * @param  list<string>  $errors
     * @param  list<int>  $productIds
     */
    private function processRow(int $index, array $item, array &$errors, array &$productIds): ?array
    {
        $position = $index + 1;

        $quantity = $item['quantity'] ?? null;
        if ($quantity === null || trim((string) $quantity) === '' || ! ctype_digit(trim((string) $quantity))) {
            $errors[] = sprintf('El elemento %d necesita una cantidad entera mayor o igual que 1.', $position);

            return null;
        }

        $quantity = (int) trim((string) $quantity);
        if ($quantity < 1) {
            $errors[] = sprintf('El elemento %d necesita una cantidad mayor o igual que 1.', $position);

            return null;
        }

        $productId = $item['product_id'] ?? null;
        if ($productId === null || trim((string) $productId) === '' || ! ctype_digit(trim((string) $productId))) {
            $errors[] = sprintf('El elemento %d necesita un producto válido del catálogo.', $position);

            return null;
        }
        $productId = (int) $productId;
        $productIds[] = $productId;

        $config = $this->decodeConfig($item['config'] ?? null);
        if ($config === null) {
            $errors[] = sprintf(
                'El elemento %d necesita una configuración JSON válida (por ejemplo {"instancia": "t3.medium"}).',
                $position,
            );

            return null;
        }

        return ['product_id' => $productId, 'quantity' => $quantity, 'config' => $config];
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
            ->with('regions')
            ->whereIn('id', array_unique($productIds))
            ->get()
            ->keyBy('id');
    }

    /**
     * Comprueba existencia, estado activo y disponibilidad en la región
     * (RN05: no se contrata un producto no disponible en la región) usando
     * una única consulta con la relación `regions` cargada.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  Collection<int, Product>  $products
     * @return list<int>
     */
    private function checkProducts(array $items, Collection $products, Region $region, array &$errors): array
    {
        $broken = [];

        foreach ($items as $index => $item) {
            $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
            if ($productId < 1 || ! $products->has($productId)) {
                continue;
            }

            $product = $products->get($productId);
            $position = $index + 1;

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

            $available = $product->regions->contains('id', $region->id);
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

    /**
     * @return array<string, mixed>|null
     */
    private function decodeConfig(mixed $rawConfig): ?array
    {
        if (is_array($rawConfig)) {
            return $rawConfig;
        }

        if (is_string($rawConfig)) {
            $rawConfig = trim($rawConfig);
            if ($rawConfig === '') {
                return [];
            }

            $decoded = json_decode($rawConfig, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
