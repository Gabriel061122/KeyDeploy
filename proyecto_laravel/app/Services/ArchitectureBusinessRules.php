<?php

namespace App\Services;

use App\Exceptions\PricingException;
use App\Models\Project;
use App\Models\Region;

/**
 * Reglas de negocio de una arquitectura manual (sección 5 de la
 * especificación): errores bloqueantes y avisos separados.
 *
 * Reutiliza `ArchitectureValidator` para las reglas que dependen del
 * catálogo y de los requisitos del proyecto, y `ArchitecturePricingService`
 * para comparar el coste inicial con el presupuesto máximo. El aviso de
 * presupuesto nunca es bloqueante.
 */
final class ArchitectureBusinessRules
{
    public function __construct(
        private ArchitectureValidator $validator,
        private ArchitecturePricingService $pricingService,
    ) {}

    /**
     * Evalúa las cinco reglas sobre los elementos crudos del formulario.
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
        $validation = $this->validator->validate($region, $items, $project);
        $errors = $validation['errors'];
        $pricing = null;

        if ($errors === []) {
            try {
                $pricing = $this->pricingService->calculate($region, $validation['items']);
            } catch (PricingException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return [
            'items' => $validation['items'],
            'errors' => $errors,
            'warnings' => $pricing !== null ? $this->budgetWarnings($project, $pricing) : [],
            'valid' => $errors === [],
            'pricing' => $pricing,
        ];
    }

    /**
     * Avisos no bloqueantes derivados de un cálculo de precios ya hecho.
     *
     * @param  array{initial_cost_cents: int}  $pricing
     * @return list<string>
     */
    public function budgetWarnings(Project $project, array $pricing): array
    {
        $maxBudgetCents = $this->maxBudgetCents($project);
        $initialCostCents = (int) $pricing['initial_cost_cents'];

        if ($initialCostCents <= $maxBudgetCents) {
            return [];
        }

        $differenceCents = $initialCostCents - $maxBudgetCents;

        return [sprintf(
            'Aviso de presupuesto: el coste inicial (%s €) supera el presupuesto máximo del proyecto (%s €) en %s €.',
            $this->formatCents($initialCostCents),
            $this->formatCents($maxBudgetCents),
            $this->formatCents($differenceCents),
        )];
    }

    private function maxBudgetCents(Project $project): int
    {
        $budget = (string) $project->getRawOriginal('max_budget');

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $budget)) {
            throw new \UnexpectedValueException('El presupuesto máximo del proyecto no tiene un formato decimal válido.');
        }

        [$euros, $fraction] = array_pad(explode('.', $budget, 2), 2, '');

        return ((int) $euros * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function formatCents(int $cents): string
    {
        return number_format($cents / 100, 2);
    }
}
