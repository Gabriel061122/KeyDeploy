<?php

namespace App\Services;

use App\Models\Architecture;
use App\Models\Project;
use App\Models\Region;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones de aplicación para la creación manual de arquitecturas.
 *
 * Concentra la validación, las reglas de negocio, el cálculo de precios
 * y la persistencia atómica del flujo manual. Delega en
 * `ArchitectureBusinessRules` (que a su vez envuelve
 * `ArchitectureValidator` y `ArchitecturePricingService`) para no
 * duplicar ninguna regla del dominio.
 */
final class ArchitectureService
{
    public function __construct(
        private ArchitectureBusinessRules $businessRules,
        private ArchitecturePricingService $pricingService,
    ) {}

    /**
     * Evalúa las reglas de negocio sobre los elementos crudos del
     * formulario: validación del dominio, cálculo de precios y avisos de
     * presupuesto. El rechazo es siempre la operación íntegra.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{
     *     items: list<array{product_id: int, quantity: int, config: array<string, mixed>}>,
     *     errors: list<string>,
     *     warnings: list<string>,
     *     valid: bool,
     *     pricing: array<string, mixed>|null
     * }
     */
    public function evaluate(Project $project, Region $region, array $items): array
    {
        return $this->businessRules->evaluate($project, $region, $items);
    }

    /**
     * Valida y guarda una arquitectura manual junto con sus elementos en
     * una sola transacción. Los identificadores de proyecto y región se
     * toman de las entidades resueltas por la aplicación, nunca del borrador.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{architecture: Architecture|null, errors: list<string>}
     */
    public function createManual(Project $project, Region $region, string $name, array $items): array
    {
        $evaluation = $this->evaluate($project, $region, $items);

        if (! $evaluation['valid']) {
            return [
                'architecture' => null,
                'errors' => $evaluation['errors'],
            ];
        }

        $architecture = DB::transaction(function () use ($project, $region, $name, $evaluation): Architecture {
            $architecture = new Architecture([
                'region_id' => $region->getKey(),
                'name' => $name,
            ]);
            $architecture->project()->associate($project);
            $architecture->source = Architecture::SOURCE_MANUAL;
            $architecture->save();

            $architecture->items()->createMany($evaluation['items']);

            return $architecture;
        });

        return [
            'architecture' => $architecture,
            'errors' => [],
        ];
    }

    /**
     * Desglose de precios de una arquitectura ya persistida.
     *
     * @return array<string, mixed>
     */
    public function pricingForArchitecture(Architecture $architecture): array
    {
        return $this->pricingService->calculateForArchitecture($architecture);
    }

    /**
     * Avisos no bloqueantes de presupuesto para un desglose ya calculado.
     *
     * @param  array{initial_cost_cents: int}  $pricing
     * @return list<string>
     */
    public function budgetWarnings(Project $project, array $pricing): array
    {
        return $this->businessRules->budgetWarnings($project, $pricing);
    }
}
