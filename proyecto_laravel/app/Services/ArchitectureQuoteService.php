<?php

namespace App\Services;

use App\Models\Architecture;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Emisión de presupuestos desde una arquitectura manual guardada.
 *
 * No lee ni modifica el carrito: reconstruye los elementos desde la
 * arquitectura persistida, vuelve a evaluar las reglas de negocio y las
 * tarifas vigentes del catálogo, y guarda cabecera y líneas históricas
 * dentro de una única transacción.
 */
final class ArchitectureQuoteService
{
    /** Modalidad congelada en las líneas de cargo único. */
    public const ONE_TIME_PRICING_MODEL = 'unico';

    public function __construct(
        private ArchitectureBusinessRules $businessRules,
    ) {}

    /**
     * Emite el presupuesto de una arquitectura perteneciente al usuario.
     *
     * @return array{quote: Quote, warnings: list<string>}
     *
     * @throws \DomainException Si la arquitectura no puede presupuestarse.
     */
    public function emitFromArchitecture(User $user, Architecture $architecture): array
    {
        $project = $architecture->project()->withTrashed()->first();

        if ($project === null || $project->trashed()) {
            throw new \DomainException('No se puede emitir un presupuesto para un proyecto eliminado.');
        }

        if ((int) $project->user_id !== (int) $user->id) {
            throw new \DomainException('No puedes emitir un presupuesto para una arquitectura de otro usuario.');
        }

        if ($architecture->source !== Architecture::SOURCE_MANUAL) {
            throw new \DomainException('Solo se puede emitir un presupuesto desde una arquitectura manual.');
        }

        $architecture->loadMissing(['region', 'items']);

        $items = $architecture->items
            ->map(fn ($item): array => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'config' => is_array($item->config) ? $item->config : [],
            ])
            ->values()
            ->all();

        if ($items === []) {
            throw new \DomainException('La arquitectura no tiene elementos para presupuestar.');
        }

        $evaluation = $this->businessRules->evaluate($project, $architecture->region, $items);

        if (! $evaluation['valid'] || $evaluation['pricing'] === null) {
            $message = implode(' ', array_unique($evaluation['errors']));

            throw new \DomainException($message !== ''
                ? $message
                : 'La arquitectura no supera las reglas de negocio y no se puede presupuestar.');
        }

        $pricing = $evaluation['pricing'];
        $configs = array_map(
            static fn (array $item): array => $item['config'],
            $evaluation['items'],
        );

        $quote = DB::transaction(function () use ($user, $project, $pricing, $configs): Quote {
            $quote = Quote::forceCreate([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'status' => 'emitido',
                'subtotal_monthly_cents' => $pricing['subtotal_monthly_cents'],
                'subtotal_one_time_cents' => $pricing['subtotal_one_time_cents'],
                'total_cents' => $pricing['initial_cost_cents'],
                'issued_at' => now(),
            ]);

            foreach ($pricing['lines'] as $index => $line) {
                $config = $configs[$index] ?? [];
                $productType = $line['product_type'] instanceof \BackedEnum
                    ? $line['product_type']->value
                    : (string) $line['product_type'];

                if ($line['monthly_total_cents'] > 0 || $line['one_time_total_cents'] === 0) {
                    QuoteLine::forceCreate([
                        'quote_id' => $quote->id,
                        'product_name' => $line['product_name'],
                        'product_type' => $productType,
                        'pricing_model' => $line['pricing_model_value'],
                        'quantity' => $line['quantity'],
                        'unit_price_cents' => $line['unit_price_cents'],
                        'line_total_cents' => $line['monthly_total_cents'],
                        'config' => $config,
                    ]);
                }

                if ($line['one_time_total_cents'] > 0) {
                    QuoteLine::forceCreate([
                        'quote_id' => $quote->id,
                        'product_name' => $line['product_name'],
                        'product_type' => $productType,
                        'pricing_model' => self::ONE_TIME_PRICING_MODEL,
                        'quantity' => $line['quantity'],
                        'unit_price_cents' => $line['one_time_unit_price_cents'],
                        'line_total_cents' => $line['one_time_total_cents'],
                        'config' => $config,
                    ]);
                }
            }

            return $quote;
        });

        return [
            'quote' => $quote,
            'warnings' => $evaluation['warnings'],
        ];
    }
}
