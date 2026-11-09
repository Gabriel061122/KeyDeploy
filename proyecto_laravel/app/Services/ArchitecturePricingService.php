<?php

namespace App\Services;

use App\Enums\PricingModel;
use App\Exceptions\PricingException;
use App\Models\Architecture;
use App\Models\Product;
use App\Models\ProductAvailability;
use App\Models\Region;
use Illuminate\Support\Collection;

/**
 * Cálculo autoritativo del coste de una arquitectura manual.
 *
 * Las tarifas se leen siempre de `product_availabilities` en el servidor:
 * el navegador solo aporta cantidades y consumos, nunca precios ni totales.
 * El subtotal mensual y el cargo único se mantienen separados; el coste
 * inicial es la suma de ambos.
 */
final class ArchitecturePricingService
{
    /** Clave de configuración con las horas mensuales de una tarifa por hora. */
    public const HOURS_FIELD = 'hours_per_month';

    /** Clave de configuración con los GB mensuales de una tarifa por GB. */
    public const GB_FIELD = 'gb_per_month';

    /**
     * Calcula el desglose de una lista de elementos de arquitectura.
     *
     * @param  array<int, array<string, mixed>>  $items  Filas con product_id, quantity y config.
     * @return array{lines: list<array<string, mixed>>, subtotal_monthly_cents: int, subtotal_one_time_cents: int, initial_cost_cents: int}
     *
     * @throws PricingException Si falta una tarifa, el consumo no es válido o el resultado no es representable.
     */
    public function calculate(Region $region, array $items): array
    {
        $products = $this->loadProducts($items);

        $lines = [];
        $subtotalMonthly = 0;
        $subtotalOneTime = 0;

        foreach ($items as $index => $item) {
            $line = $this->calculateItem($region, $item, $index + 1, $products);

            $lines[] = $line;
            $subtotalMonthly = $this->safeAdd(
                $subtotalMonthly,
                $line['monthly_total_cents'],
                'El subtotal mensual de la arquitectura supera el importe máximo representable.',
            );
            $subtotalOneTime = $this->safeAdd(
                $subtotalOneTime,
                $line['one_time_total_cents'],
                'El pago único de la arquitectura supera el importe máximo representable.',
            );
        }

        return [
            'lines' => $lines,
            'subtotal_monthly_cents' => $subtotalMonthly,
            'subtotal_one_time_cents' => $subtotalOneTime,
            'initial_cost_cents' => $this->safeAdd(
                $subtotalMonthly,
                $subtotalOneTime,
                'El coste inicial de la arquitectura supera el importe máximo representable.',
            ),
        ];
    }

    /**
     * Calcula el desglose de una arquitectura ya guardada.
     *
     * @return array{lines: list<array<string, mixed>>, subtotal_monthly_cents: int, subtotal_one_time_cents: int, initial_cost_cents: int}
     *
     * @throws PricingException
     */
    public function calculateForArchitecture(Architecture $architecture): array
    {
        $architecture->loadMissing(['region', 'items']);

        $items = $architecture->items
            ->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'config' => is_array($item->config) ? $item->config : [],
            ])
            ->all();

        return $this->calculate($architecture->region, $items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, Product>
     */
    private function loadProducts(array $items): Collection
    {
        $productIds = array_values(array_unique(array_filter(array_map(
            fn (array $item): int => (int) ($item['product_id'] ?? 0),
            $items,
        ))));

        if ($productIds === []) {
            return collect();
        }

        return Product::query()
            ->with(['provider', 'availabilities'])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<string, mixed>
     *
     * @throws PricingException
     */
    private function calculateItem(Region $region, array $item, int $position, Collection $products): array
    {
        $productId = (int) ($item['product_id'] ?? 0);
        $quantity = (int) ($item['quantity'] ?? 0);

        if ($quantity < 1) {
            throw new PricingException(sprintf(
                'El elemento %d necesita una cantidad entera mayor o igual que 1.',
                $position,
            ));
        }

        $product = $products->get($productId);
        if ($product === null) {
            throw new PricingException(sprintf(
                'El elemento %d usa el producto %d, que no existe en el catálogo.',
                $position,
                $productId,
            ));
        }

        $element = sprintf('El elemento %d ("%s")', $position, $product->name);
        $availability = $product->availabilities->firstWhere('region_id', $region->id);

        if ($availability === null || ! $availability->available) {
            throw new PricingException(sprintf(
                '%s no tiene una tarifa disponible en la región "%s".',
                $element,
                $region->name,
            ));
        }

        $pricingModel = $this->resolvePricingModel($availability, $element, $region);
        $priceCents = $this->resolveCents(
            $availability,
            'price_cents',
            sprintf('%s no tiene un precio válido en la región "%s".', $element, $region->name),
        );
        $oneTimeUnitCents = $this->resolveCents(
            $availability,
            'one_time_price_cents',
            sprintf('%s no tiene un cargo único válido en la región "%s".', $element, $region->name),
            allowMissing: true,
        );

        $config = $this->normalizeConfig($item['config'] ?? null, $position);

        $usageHundredths = null;
        $usageField = null;

        if ($pricingModel === PricingModel::PorHora) {
            $usageField = self::HOURS_FIELD;
        } elseif ($pricingModel === PricingModel::PorGb) {
            $usageField = self::GB_FIELD;
        }

        if ($usageField !== null) {
            if (! array_key_exists($usageField, $config)) {
                throw new PricingException(sprintf(
                    '%s usa la modalidad "%s" y necesita el consumo "%s" en su configuración.',
                    $element,
                    $pricingModel->label(),
                    $usageField,
                ));
            }

            $usageHundredths = $this->parseUsageHundredths($config[$usageField], $usageField, $element);
            $rawMonthly = $this->safeMultiply(
                $priceCents,
                $usageHundredths,
                sprintf('%s supera el importe máximo representable al calcular su consumo mensual.', $element),
            );
            $rawMonthly = $this->safeMultiply(
                $rawMonthly,
                $quantity,
                sprintf('%s supera el importe máximo representable al calcular su cantidad.', $element),
            );
            $monthlyTotal = $this->roundHundredthsToCents($rawMonthly);
        } else {
            $monthlyTotal = $this->safeMultiply(
                $priceCents,
                $quantity,
                sprintf('%s supera el importe máximo representable al calcular su subtotal mensual.', $element),
            );
        }

        $oneTimeTotal = $this->safeMultiply(
            $oneTimeUnitCents,
            $quantity,
            sprintf('%s supera el importe máximo representable al calcular su cargo único.', $element),
        );

        return [
            'position' => $position,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_code' => $product->code,
            'product_type' => $product->type,
            'product_type_label' => $product->typeEnum()?->label() ?? (string) $product->type,
            'provider_name' => $product->provider?->name,
            'pricing_model' => $pricingModel,
            'pricing_model_value' => $pricingModel->value,
            'pricing_model_label' => $pricingModel->label(),
            'quantity' => $quantity,
            'usage_field' => $usageField,
            'usage' => $usageHundredths !== null ? $usageHundredths / 100 : null,
            'unit_price_cents' => $priceCents,
            'one_time_unit_price_cents' => $oneTimeUnitCents,
            'monthly_total_cents' => $monthlyTotal,
            'one_time_total_cents' => $oneTimeTotal,
        ];
    }

    /**
     * @throws PricingException
     */
    private function resolvePricingModel(ProductAvailability $availability, string $element, Region $region): PricingModel
    {
        $rawModel = (string) $availability->getRawOriginal('pricing_model');
        $pricingModel = PricingModel::tryFrom($rawModel);

        if ($pricingModel === null) {
            throw new PricingException(sprintf(
                '%s tiene una modalidad de tarifa no válida ("%s") en la región "%s".',
                $element,
                $rawModel,
                $region->name,
            ));
        }

        return $pricingModel;
    }

    /**
     * @throws PricingException
     */
    private function resolveCents(
        ProductAvailability $availability,
        string $attribute,
        string $message,
        bool $allowMissing = false,
    ): int {
        $raw = $availability->getRawOriginal($attribute);

        if ($raw === null && $allowMissing) {
            return 0;
        }

        if ($raw === null || ! is_numeric($raw) || (int) $raw < 0) {
            throw new PricingException($message);
        }

        return (int) $raw;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PricingException
     */
    private function normalizeConfig(mixed $config, int $position): array
    {
        if (is_array($config)) {
            return $config;
        }

        if (is_string($config)) {
            $trimmed = trim($config);

            if ($trimmed === '') {
                return [];
            }

            try {
                $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new PricingException(sprintf(
                    'El elemento %d contiene una configuración JSON no válida.',
                    $position,
                ));
            }

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new PricingException(sprintf(
            'La configuración del elemento %d debe ser un objeto JSON válido.',
            $position,
        ));
    }

    /**
     * Convierte un consumo mensual a centésimas de unidad para calcular con
     * enteros y redondear una sola vez por línea.
     *
     * @throws PricingException
     */
    private function parseUsageHundredths(mixed $value, string $field, string $element): int
    {
        $invalid = sprintf(
            '%s tiene un consumo "%s" no válido: debe ser un número no negativo con hasta dos decimales.',
            $element,
            $field,
        );

        if (is_bool($value) || ! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new PricingException($invalid);
        }

        $normalized = is_string($value) ? trim($value) : (string) $value;

        if (! preg_match('/^\d{1,17}(?:\.\d{1,2})?$/', $normalized)) {
            throw new PricingException($invalid);
        }

        [$integerPart, $decimalPart] = array_pad(explode('.', $normalized, 2), 2, '');

        $integerPart = ltrim($integerPart, '0');
        $integerPart = $integerPart === '' ? '0' : $integerPart;

        if (strlen($integerPart) > 17) {
            throw new PricingException(sprintf(
                '%s tiene un consumo "%s" demasiado grande.',
                $element,
                $field,
            ));
        }

        $integerHundredths = $this->safeMultiply(
            (int) $integerPart,
            100,
            sprintf('%s tiene un consumo "%s" demasiado grande.', $element, $field),
        );

        return $this->safeAdd(
            $integerHundredths,
            (int) str_pad($decimalPart, 2, '0'),
            sprintf('%s tiene un consumo "%s" demasiado grande.', $element, $field),
        );
    }

    /**
     * Redondea centésimas de céntimo al céntimo más próximo, con empate
     * hacia arriba.
     */
    private function roundHundredthsToCents(int $rawHundredths): int
    {
        $cents = intdiv($rawHundredths, 100);

        return $rawHundredths % 100 >= 50 ? $cents + 1 : $cents;
    }

    /**
     * @throws PricingException
     */
    private function safeMultiply(int $left, int $right, string $message): int
    {
        if ($left < 0 || $right < 0 || ($left !== 0 && $right > intdiv(PHP_INT_MAX, $left))) {
            throw new PricingException($message);
        }

        return $left * $right;
    }

    /**
     * @throws PricingException
     */
    private function safeAdd(int $left, int $right, string $message): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new PricingException($message);
        }

        return $left + $right;
    }
}
