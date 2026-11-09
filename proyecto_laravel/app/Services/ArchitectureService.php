<?php

namespace App\Services;

use App\Models\Architecture;
use App\Models\Project;
use App\Models\Region;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones de aplicación para la creación manual de arquitecturas.
 */
final class ArchitectureService
{
    public function __construct(private ArchitectureValidator $architectureValidator) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: list<array{product_id: int, quantity: int, config: array<string, mixed>}>, errors: list<string>, valid: bool}
     */
    public function validate(Region $region, array $items): array
    {
        return $this->architectureValidator->validate($region, $items);
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
        $result = $this->validate($region, $items);

        if (! $result['valid']) {
            return [
                'architecture' => null,
                'errors' => $result['errors'],
            ];
        }

        $architecture = DB::transaction(function () use ($project, $region, $name, $result): Architecture {
            $architecture = new Architecture([
                'region_id' => $region->getKey(),
                'name' => $name,
            ]);
            $architecture->project()->associate($project);
            $architecture->source = Architecture::SOURCE_MANUAL;
            $architecture->save();

            $architecture->items()->createMany($result['items']);

            return $architecture;
        });

        return [
            'architecture' => $architecture,
            'errors' => [],
        ];
    }
}
