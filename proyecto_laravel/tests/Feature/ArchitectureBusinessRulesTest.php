<?php

namespace Tests\Feature;

use App\Models\Architecture;
use App\Models\ArchitectureItem;
use App\Models\Product;
use App\Models\ProductAvailability;
use App\Models\Project;
use App\Models\Provider;
use App\Models\Region;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\ExpectationFailedException;
use Tests\TestCase;

/**
 * feature/architecture-business-rules: las cinco reglas de negocio al
 * revisar/guardar una arquitectura manual (criterios de aceptación 6 a 10,
 * sección 5 de la especificación).
 *
 * Igual que en las pruebas del cálculo, las reglas se observan desde el
 * flujo externo sin JavaScript: revisar (POST) → comprobación (GET) →
 * guardar (POST). Nunca se acopla a la forma interna de ningún servicio:
 * un bloqueo es una respuesta con errores de sesión y cero filas
 * persistidas; una regla satisfecha es una revisión sin errores y un
 * guardado real en la base de datos.
 *
 * Las reglas 1 y 2 y la parte de disponibilidad/inactivo/propiedad de la
 * regla 3 ya están observadas por las pruebas preexistentes de
 * arquitecturas (ArchitectureTest); este fichero prueba lo nuevo de esta
 * etapa: igualdad entre la región del proyecto y la de la arquitectura,
 * evaluación de requerimientos (regla 4) con las semánticas de
 * RequirementRules, claves repetidas donde basta un valor que cumpla,
 * aviso no bloqueante de presupuesto por coste inicial y revalidación
 * antes de guardar. La emisión de presupuestos es la etapa siguiente y
 * queda fuera del alcance.
 */
class ArchitectureBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Criterio 8 y regla 3: la región de la arquitectura debe ser la
     * región requerida por el proyecto, aunque la región enviada exista
     * y TODOS los productos existan, estén activos y tengan tarifa
     * disponible en ella (así el único bloqueo posible es la igualdad
     * con la región del proyecto).
     */
    public function test_review_rejects_a_region_other_than_the_project_region(): void
    {
        $user = User::factory()->create();
        $projectRegion = $this->regionFor('proyecto');
        $otherRegion = $this->regionFor('enviada');
        $project = $this->projectFor($user, $projectRegion);

        $items = [
            ['product_id' => $this->productWithTariff($projectRegion, 'Servidor global', 'computacion', 900, extraRegions: [$otherRegion])->id, 'quantity' => 1],
            ['product_id' => $this->productWithTariff($projectRegion, 'Disco global', 'almacenamiento', 500, extraRegions: [$otherRegion])->id, 'quantity' => 2],
            ['product_id' => $this->productWithTariff($projectRegion, 'Firewall global', 'redes', 300, extraRegions: [$otherRegion])->id, 'quantity' => 1],
        ];

        $errors = $this->postReview($project, $items, $user, regionId: $otherRegion->id);

        // Bloqueo con la región causante identificable: el mensaje cita
        // la región enviada, la exigida por el proyecto, o ambas.
        $this->assertMessagesMention($errors, [$otherRegion->name, $projectRegion->name], mode: 'any');

        // No se persiste nada y no queda borrador pendiente de revisión.
        $this->assertNothingPersisted();
        $this->get(route('projects.architectures.check', $project))->assertRedirect();
    }

    /**
     * Criterio 9 y contrato 5.1: operadores de texto, numéricos y
     * decimales de RequirementRules con semántica numérica real para los
     * números (nunca comparación lexicográfica de cadenas) y cadenas
     * sensibles a mayúsculas/minúsculas. Casos permitidos → revisión sin
     * errores y guardado real; casos bloqueados → errores citando la
     * clave del requisito y nada persistido.
     */
    public function test_requirement_operator_matrix_enforced_at_review(): void
    {
        $cases = [
            // [descripción, data_type, operador, valor, campo, atributos del catálogo, ¿bloquea?]
            ['gte 10 vs 9: orden numérico, no lexicográfico', 'integer', 'gte', '9', 'nucleos', ['nucleos' => '10'], false],
            ['gte 8 vs 8: borde inclusivo', 'integer', 'gte', '8', 'nucleos', ['nucleos' => '8'], false],
            ['gte 9 vs 8: incumplido', 'integer', 'gte', '9', 'nucleos', ['nucleos' => '8'], true],
            ['eq 10 vs "010": igualdad numérica', 'integer', 'eq', '10', 'nucleos', ['nucleos' => '010'], false],
            ['eq 10 vs 11 con número JSON', 'integer', 'eq', '10', 'nucleos', ['nucleos' => 11], true],
            ['neq 10 vs 11: diferente numérico', 'integer', 'neq', '10', 'nucleos', ['nucleos' => '11'], false],
            ['neq 10 vs 10: igualdad bloquea', 'integer', 'neq', '10', 'nucleos', ['nucleos' => 10], true],
            ['gt 9 vs "10": orden numérico', 'integer', 'gt', '9', 'nucleos', ['nucleos' => '10'], false],
            ['lt 100 vs 99', 'integer', 'lt', '100', 'nucleos', ['nucleos' => 99], false],
            ['decimal gt 3.5 vs 3.75', 'decimal', 'gt', '3.5', 'memoria', ['memoria' => '3.75'], false],
            ['decimal gt 3.5 vs 3.49: incumplido', 'decimal', 'gt', '3.5', 'memoria', ['memoria' => '3.49'], true],
            ['decimal lte 16.5 vs 16.5: borde inclusivo', 'decimal', 'lte', '16.5', 'memoria', ['memoria' => '16.5'], false],
            ['decimal neq 3.5 vs 3.75', 'decimal', 'neq', '3.5', 'memoria', ['memoria' => '3.75'], false],
            ['string eq exacto', 'string', 'eq', 'Linux', 'so', ['so' => 'Linux'], false],
            ['string eq sensible a mayúsculas', 'string', 'eq', 'Linux', 'so', ['so' => 'linux'], true],
            ['string neq sensible a mayúsculas', 'string', 'neq', 'Linux', 'so', ['so' => 'linux'], false],
            ['string neq igualdad bloquea', 'string', 'neq', 'Linux', 'so', ['so' => 'Linux'], true],
            ['string contains satisfecho', 'string', 'contains', '16', 'software', ['software' => 'Postgres 16'], false],
            ['string contains incumplido', 'string', 'contains', 'ubuntu', 'software', ['software' => 'Debian 12'], true],
            ['string not_contains satisfecho', 'string', 'not_contains', 'oracle', 'software', ['software' => 'Postgres 16'], false],
            ['string not_contains incumplido', 'string', 'not_contains', 'oracle', 'software', ['software' => 'oracle 19c'], true],
        ];

        foreach ($cases as $index => [$case, $dataType, $operator, $value, $field, $attributes, $mustBlock]) {
            $user = User::factory()->create();
            $region = $this->regionFor('req-'.$index);
            $project = $this->projectFor($user, $region);

            $this->requirementFor($project, $field, $operator, $value, $dataType, mandatory: true);

            // El requisito obligatorio se comprueba contra los ATRIBUTOS
            // del catálogo (products.config) de los productos
            // seleccionados, nunca contra JSON del navegador.
            $items = [
                ['product_id' => $this->productWithTariff($region, 'Disco evaluado', 'almacenamiento', 500, attributes: $attributes)->id, 'quantity' => 1],
                ['product_id' => $this->productWithTariff($region, 'Servidor de relleno', 'computacion', 400)->id, 'quantity' => 2],
                ['product_id' => $this->productWithTariff($region, 'Firewall de relleno', 'redes', 300)->id, 'quantity' => 1],
            ];
            $architecturesBefore = Architecture::query()->count();
            $itemsBefore = ArchitectureItem::query()->count();

            if ($mustBlock) {
                $errors = $this->postReview($project, $items, $user, context: $case);
                $this->assertMessagesMention($errors, [$field], mode: 'all', context: $case);
                $this->assertSame($architecturesBefore, Architecture::query()->count());
                $this->assertSame($itemsBefore, ArchitectureItem::query()->count());

                continue;
            }

            $this->reviewOk($project, $items, $user, context: $case);
            $this->get(route('projects.architectures.check', $project))->assertOk();
            $this->post(route('projects.architectures.store', $project));

            $this->assertSame(
                $architecturesBefore + 1,
                Architecture::query()->count(),
                'Caso "'.$case.'": la revisión satisfecha debe permitir guardar.',
            );
        }
    }

    /**
     * Contrato 2.2: un requisito obligatorio sin ningún producto
     * seleccionado que declare la clave en sus atributos del catálogo
     * (products.config) bloquea.
     */
    public function test_mandatory_requirement_without_any_matching_key_blocks_review(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('sin-clave');
        $project = $this->projectFor($user, $region);

        $this->requirementFor($project, 'nucleos', 'gte', '8', 'integer', mandatory: true);

        $errors = $this->postReview($project, [
            ['product_id' => $this->productWithTariff($region, 'Disco vacío', 'almacenamiento', 500)->id, 'quantity' => 1],
            ['product_id' => $this->productWithTariff($region, 'Servidor vacío', 'computacion', 400)->id, 'quantity' => 2],
            ['product_id' => $this->productWithTariff($region, 'Firewall vacío', 'redes', 300)->id, 'quantity' => 1],
        ], $user);

        $this->assertMessagesMention($errors, ['nucleos'], mode: 'all', context: 'sin-clave');
        $this->assertNothingPersisted();
    }

    /**
     * Contrato 2.2: los booleanos se normalizan con las convenciones
     * existentes de RequirementRules ('0'/'1', true/false, 'verdadero',
     * 'no', …) al comparar los ATRIBUTOS del catálogo
     * (products.config) con el requisito. Los valores del requisito
     * están ya normalizados por el formulario ('1' y '0');
     * los valores de los atributos del catálogo llegan al evaluador tal
     * y como los sembró el proveedor (JSON: true/false, '1', 'no', …).
     */
    public function test_boolean_comparison_uses_existing_normalization_conventions(): void
    {
        $cases = [
            // [atributo del catálogo (products.config), operador, requerido, ¿satisface?]
            ['{"europeo": true}', 'eq', '1', true],
            ['{"europeo": "verdadero"}', 'eq', '1', true],
            ['{"europeo": 1}', 'eq', '1', true],
            ['{"europeo": false}', 'eq', '1', false],
            ['{"europeo": "no"}', 'eq', '1', false],
            ['{"europeo": "falso"}', 'eq', '1', false],
            ['{"europeo": false}', 'neq', '1', true],
            ['{"europeo": "verdadero"}', 'neq', '1', false],
        ];

        foreach ($cases as $index => [$attributesJson, $operator, $requiredValue, $satisfies]) {
            $user = User::factory()->create();
            $region = $this->regionFor('bool-'.$index);
            $project = $this->projectFor($user, $region);

            $this->requirementFor($project, 'europeo', $operator, $requiredValue, 'boolean', mandatory: true);

            $items = [
                ['product_id' => $this->productWithTariff($region, 'Disco bool', 'almacenamiento', 500, attributes: (array) json_decode($attributesJson, true))->id, 'quantity' => 1],
                ['product_id' => $this->productWithTariff($region, 'Servidor bool', 'computacion', 400)->id, 'quantity' => 2],
                ['product_id' => $this->productWithTariff($region, 'Firewall bool', 'redes', 300)->id, 'quantity' => 1],
            ];

            if ($satisfies) {
                $this->reviewOk($project, $items, $user, context: 'bool '.$attributesJson);
                $this->get(route('projects.architectures.check', $project))->assertOk();
            } else {
                $errors = $this->postReview($project, $items, $user, context: 'bool '.$attributesJson);
                $this->assertMessagesMention($errors, ['europeo'], mode: 'all', context: 'bool '.$attributesJson);
                $this->assertNothingPersisted();
            }
        }
    }

    /**
     * Criterio 9, contrato 5.1: cuando la misma clave de primer nivel
     * aparece en varios elementos basta con que AL MENOS UN valor del
     * conjunto satisfaga el requisito.
     */
    public function test_repeated_keys_any_single_matching_value_satisfies_the_requirement(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('repetidas-ok');
        $project = $this->projectFor($user, $region);

        $this->requirementFor($project, 'nucleos', 'gte', '8', 'integer', mandatory: true);

        // Con atributos del catálogo basta con que AL MENOS UN producto
        // seleccionado declare la clave con un valor que cumpla.
        $items = [
            ['product_id' => $this->productWithTariff($region, 'Disco pequeño', 'almacenamiento', 500, attributes: ['nucleos' => '4'])->id, 'quantity' => 1],
            ['product_id' => $this->productWithTariff($region, 'Servidor grande', 'computacion', 400, attributes: ['nucleos' => '16'])->id, 'quantity' => 1],
            ['product_id' => $this->productWithTariff($region, 'Firewall sin datos', 'redes', 300)->id, 'quantity' => 1],
        ];

        $this->reviewOk($project, $items, $user);
        $this->get(route('projects.architectures.check', $project))->assertOk();
        $this->post(route('projects.architectures.store', $project));

        $this->assertSame(1, Architecture::query()->count());
    }

    /**
     * El espejo del caso anterior: si NINGÚN valor de los elementos con
     * la clave repetida satisface el requisito, se bloquea citando la
     * clave y nada queda persistido.
     */
    public function test_repeated_keys_when_no_value_matches_blocks_the_requirement(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('repetidas-ko');
        $project = $this->projectFor($user, $region);

        $this->requirementFor($project, 'nucleos', 'gte', '8', 'integer', mandatory: true);

        $errors = $this->postReview($project, [
            ['product_id' => $this->productWithTariff($region, 'Disco cuatro', 'almacenamiento', 500, attributes: ['nucleos' => '4'])->id, 'quantity' => 1],
            ['product_id' => $this->productWithTariff($region, 'Servidor seis', 'computacion', 400, attributes: ['nucleos' => '6'])->id, 'quantity' => 1],
            ['product_id' => $this->productWithTariff($region, 'Firewall sin datos', 'redes', 300)->id, 'quantity' => 1],
        ], $user);

        $this->assertMessagesMention($errors, ['nucleos'], mode: 'all', context: 'repetidas-ko');
        $this->assertNothingPersisted();
    }

    /**
     * Criterio 9: un requisito OPCIONAL incumplido no bloquea la
     * revisión ni el guardado (ni con la clave presente con otro valor
     * ni sin la clave).
     */
    public function test_optional_requirements_that_are_not_satisfied_do_not_block(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('opcional');
        $project = $this->projectFor($user, $region);

        $this->requirementFor($project, 'nucleos', 'gte', '8', 'integer', mandatory: false);
        $this->requirementFor($project, 'so', 'eq', 'Linux', 'string', mandatory: false);

        $items = [
            ['product_id' => $this->productWithTariff($region, 'Disco opcional', 'almacenamiento', 500, attributes: ['nucleos' => '2'])->id, 'quantity' => 1],
            ['product_id' => $this->productWithTariff($region, 'Servidor opcional', 'computacion', 400, attributes: ['so' => 'windows'])->id, 'quantity' => 2],
            ['product_id' => $this->productWithTariff($region, 'Firewall opcional', 'redes', 300)->id, 'quantity' => 1],
        ];

        $this->reviewOk($project, $items, $user);
        $this->get(route('projects.architectures.check', $project))->assertOk();
        $this->post(route('projects.architectures.store', $project));

        $this->assertSame(1, Architecture::query()->count());
    }

    /**
     * Criterio 7 y decisión 3: si el coste inicial supera
     * `Project.max_budget` se muestra un aviso (con ambos importes y la
     * diferencia) y el flujo permite continuar y guardar.
     *
     * Importes: 1555¢×2 + 1001¢ + 1¢ = mensual 4112¢ (41.12 €); cargo
     * único 1061¢×2 = 2122¢ (21.22 €); coste inicial 6234¢ = 62.34 €;
     * presupuesto 57.77 € → diferencia 4.57 €.
     */
    public function test_over_max_budget_shows_the_warning_and_allows_continuing_and_saving(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('presupuesto');
        $project = $this->projectFor($user, $region, maxBudget: '57.77');

        $server = $this->productWithTariff($region, 'Servidor costoso', 'computacion', 1555, oneTimeCents: 1061);
        $disk = $this->productWithTariff($region, 'Disco costoso', 'almacenamiento', 1001);
        $network = $this->productWithTariff($region, 'Firewall barato', 'redes', 1);

        $items = [
            ['product_id' => $server->id, 'quantity' => 2],
            ['product_id' => $disk->id, 'quantity' => 1],
            ['product_id' => $network->id, 'quantity' => 1],
        ];

        $this->reviewOk($project, $items, $user);
        $check = $this->get(route('projects.architectures.check', $project))->assertOk();

        // El aviso muestra coste inicial, presupuesto y diferencia.
        $this->assertSeesAmount($check, 6234); // coste inicial
        $this->assertSeesAmount($check, 5777); // max_budget
        $this->assertSeesAmount($check, 457);  // 62.34 − 57.77

        // Y permite guardar la arquitectura tal cual.
        $this->post(route('projects.architectures.store', $project));
        $this->assertSame(1, Architecture::query()->count());
    }

    /**
     * Criterio 7 y decisión 3: el presupuesto se compara con el coste
     * INICIAL, no solo con el mensual. Aquí el mensual (41.12 €) está
     * por debajo del presupuesto (45.00 €) pero el cargo único de
     * instalación lo empuja por encima (62.36 €): el aviso debe mostrar
     * ambos importes y la diferencia de 17.36 €. Una comparación contra
     * el mensual solo no generaría aviso (17.36 €/45,00 € ausentes).
     */
    public function test_warning_is_based_on_the_initial_cost_not_only_on_the_monthly_subtotal(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('coste-inicial');
        $project = $this->projectFor($user, $region, maxBudget: '45.00');

        $server = $this->productWithTariff($region, 'Servidor con instalación', 'computacion', 1555, oneTimeCents: 1062);
        $disk = $this->productWithTariff($region, 'Disco con instalación', 'almacenamiento', 1001);
        $network = $this->productWithTariff($region, 'Firewall con instalación', 'redes', 1);

        $items = [
            ['product_id' => $server->id, 'quantity' => 2],
            ['product_id' => $disk->id, 'quantity' => 1],
            ['product_id' => $network->id, 'quantity' => 1],
        ];

        $this->reviewOk($project, $items, $user);
        $check = $this->get(route('projects.architectures.check', $project))->assertOk();

        // Mensual 4112¢ (visible por el desglose del cálculo) sin el
        // cargo único estaría por debajo del presupuesto.
        $this->assertSeesAmount($check, 4112);

        // Aviso por coste inicial: 62.36 € frente a 45.00 €, diferencia 17.36 €.
        $this->assertSeesAmount($check, 6236);
        $this->assertSeesAmount($check, 4500);
        $this->assertSeesAmount($check, 1736);
    }

    /**
     * Sección 5 y criterio 7: el aviso de presupuesto nunca bloquea el
     * guardado. Si el presupuesto se reduce tras la revisión (el coste
     * inicial 62.34 € pasa por encima mientras el borrador espera), el
     * guardado sigue permitido.
     */
    public function test_save_still_allows_saving_when_the_budget_overage_appears_after_review(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('presupuesto-guardado');
        $project = $this->projectFor($user, $region, maxBudget: '99.99');

        $server = $this->productWithTariff($region, 'Servidor sobre presupuesto', 'computacion', 1555, oneTimeCents: 1061);
        $disk = $this->productWithTariff($region, 'Disco sobre presupuesto', 'almacenamiento', 1001);
        $network = $this->productWithTariff($region, 'Firewall sobre presupuesto', 'redes', 1);

        $items = [
            ['product_id' => $server->id, 'quantity' => 2],
            ['product_id' => $disk->id, 'quantity' => 1],
            ['product_id' => $network->id, 'quantity' => 1],
        ];

        $this->reviewOk($project, $items, $user);

        $project->update(['max_budget' => '10.00']);

        $this->post(route('projects.architectures.store', $project))->assertRedirect();
        $this->assertSame(1, Architecture::query()->count());
        $this->assertSame(3, Architecture::query()->firstOrFail()->items()->count());
    }

    /**
     * Criterio 6 y sección 6: la revalidación antes de guardar vuelve a
     * comprobar el estado del catálogo. Un producto retirado entre la
     * revisión y el cierre impide guardar; nada queda persistido.
     */
    public function test_save_revalidation_rejects_a_product_deactivated_after_review(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('revalidar-retiro');
        $project = $this->projectFor($user, $region, maxBudget: '99.99');

        $disk = $this->productWithTariff($region, 'Disco retirado', 'almacenamiento', 400);
        $server = $this->productWithTariff($region, 'Servidor conservado', 'computacion', 500);
        $network = $this->productWithTariff($region, 'Firewall conservado', 'redes', 300);

        $items = [
            ['product_id' => $server->id, 'quantity' => 1],
            ['product_id' => $disk->id, 'quantity' => 1],
            ['product_id' => $network->id, 'quantity' => 1],
        ];

        $this->reviewOk($project, $items, $user);

        // El proveedor retira el producto después de la revisión: el
        // borrador validado deja de ser válido al guardar.
        Product::query()->whereKey($disk->id)->update(['active' => false]);

        $this->post(route('projects.architectures.store', $project))->assertRedirect();

        $errors = $this->allSessionErrors();
        $this->assertMessagesMention($errors, [$disk->name, $disk->code], mode: 'any', context: 'producto retirado');
        $this->assertNothingPersisted();
    }

    /**
     * Criterio 8 y regla 3 en el guardado: la disponibilidad desaparece
     * de la región entre la revisión y el cierre; el guardado la
     * rechaza y nada queda persistido.
     */
    public function test_save_revalidation_rejects_an_availability_that_becomes_unavailable(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('revalidar-dispo');
        $project = $this->projectFor($user, $region, maxBudget: '99.99');

        $disk = $this->productWithTariff($region, 'Disco sin disponibilidad', 'almacenamiento', 400);
        $server = $this->productWithTariff($region, 'Servidor con disponibilidad', 'computacion', 500);
        $network = $this->productWithTariff($region, 'Firewall con disponibilidad', 'redes', 300);

        $items = [
            ['product_id' => $server->id, 'quantity' => 1],
            ['product_id' => $disk->id, 'quantity' => 1],
            ['product_id' => $network->id, 'quantity' => 1],
        ];

        $this->reviewOk($project, $items, $user);

        ProductAvailability::query()
            ->where('product_id', $disk->id)
            ->where('region_id', $region->id)
            ->update(['available' => false]);

        $this->post(route('projects.architectures.store', $project))->assertRedirect();

        $this->assertNothingPersisted();
    }

    /**
     * Regla 4 y revalidación en el guardado (criterio 6): un
     * requerimiento obligatorio añadido DESPUÉS de la revisión invalida
     * el borrador; el guardado se bloquea citando la clave y nada queda
     * persistido.
     */
    public function test_save_revalidation_rejects_a_mandatory_requirement_added_after_review(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('revalidar-requisito');
        $project = $this->projectFor($user, $region, maxBudget: '99.99');

        $items = [
            ['product_id' => $this->productWithTariff($region, 'Servidor revalidado', 'computacion', 500)->id, 'quantity' => 1],
            ['product_id' => $this->productWithTariff($region, 'Disco revalidado', 'almacenamiento', 400)->id, 'quantity' => 1],
            ['product_id' => $this->productWithTariff($region, 'Firewall revalidado', 'redes', 300)->id, 'quantity' => 1],
        ];

        $this->reviewOk($project, $items, $user);

        $this->requirementFor($project, 'nucleos', 'gte', '32', 'integer', mandatory: true);

        $this->post(route('projects.architectures.store', $project))->assertRedirect();

        $errors = $this->allSessionErrors();
        $this->assertMessagesMention($errors, ['nucleos'], mode: 'all', context: 'revalidación de requerimientos');
        $this->assertNothingPersisted();
    }

    /**
     * Regla 3 en el guardado: si la región requerida del proyecto cambia
     * entre la revisión y el cierre, el borrador ya no satisface la
     * igualdad exigida y el guardado se bloquea.
     */
    public function test_save_revalidation_keeps_the_region_equal_to_the_project_region(): void
    {
        $user = User::factory()->create();
        $originalRegion = $this->regionFor('region-original');
        $project = $this->projectFor($user, $originalRegion, maxBudget: '99.99');

        $items = [
            ['product_id' => $this->productWithTariff($originalRegion, 'Servidor revalidado', 'computacion', 500)->id, 'quantity' => 1],
            ['product_id' => $this->productWithTariff($originalRegion, 'Disco revalidado', 'almacenamiento', 400)->id, 'quantity' => 1],
            ['product_id' => $this->productWithTariff($originalRegion, 'Firewall revalidado', 'redes', 300)->id, 'quantity' => 1],
        ];

        $this->reviewOk($project, $items, $user);

        // El proyecto se reasigna a otra región después de la revisión.
        $newRegion = $this->regionFor('region-nueva');
        $project->update(['region_id' => $newRegion->id]);

        $this->post(route('projects.architectures.store', $project))->assertRedirect();

        $errors = $this->allSessionErrors();
        $this->assertMessagesMention(
            $errors,
            [$newRegion->name, $originalRegion->name],
            mode: 'any',
            context: 'revalidación de región',
        );
        $this->assertNothingPersisted();
    }

    /**
     * Envía la revisión esperando el bloqueo de las reglas y devuelve
     * todos los mensajes de error de la sesión (independiente de la
     * clave del error bag). Debuelve también el fallo del test si la
     * revisión no llega a bloquear.
     *
     * @param  list<array{product_id: int, quantity: int, usage?: string}>  $items
     * @return list<string>
     */
    private function postReview(Project $project, array $items, User $user, ?int $regionId = null, ?string $context = null): array
    {
        $response = $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, $items, $regionId));

        $context ??= 'revisión bloqueada';

        try {
            $response->assertSessionHasErrors();
        } catch (ExpectationFailedException $exception) {
            $this->fail('La revisión debería bloquearse ('.$context.'): '.$exception->getMessage());
        }

        return $this->allSessionErrors();
    }

    /**
     * Envía la revisión esperando el paso satisfactorio de las reglas:
     * sin errores de sesión y redirección a la comprobación.
     *
     * @param  list<array{product_id: int, quantity: int, usage?: string}>  $items
     */
    private function reviewOk(Project $project, array $items, User $user, ?int $regionId = null, ?string $context = null): void
    {
        $response = $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, $items, $regionId));

        $context ??= 'revisión satisfactoria';

        try {
            $response->assertSessionHasNoErrors();
            $response->assertRedirect(route('projects.architectures.check', $project));
        } catch (ExpectationFailedException $exception) {
            $this->fail('La revisión debería pasar ('.$context.'): '.$exception->getMessage()
                .' Errores: '.implode(' | ', $this->allSessionErrors()));
        }
    }

    /**
     * @return list<string>
     */
    private function allSessionErrors(): array
    {
        $bag = session('errors');

        if ($bag === null) {
            return [];
        }

        if ($bag instanceof ViewErrorBag) {
            return $bag->all();
        }

        if ($bag instanceof MessageBag) {
            return $bag->all();
        }

        if (is_array($bag)) {
            return collect($bag)->flatten()->all();
        }

        return [$bag];
    }

    /**
     * Comprueba que los mensajes de error citan las causas dadas
     * (motivo identificable del criterio 6).
     *
     * @param  list<string>  $errors
     * @param  list<string>  $needles
     * @param  string  $mode  'all' exige cada aguja en algún mensaje;
     *                        'any' exige al menos una aguja en alguno.
     */
    private function assertMessagesMention(array $errors, array $needles, string $mode = 'all', ?string $context = null): void
    {
        $this->assertNotEmpty(
            $errors,
            'El bloqueo debe producir mensajes de error'.($context ? ' — '.$context : ''),
        );

        foreach ($needles as $needle) {
            $found = false;

            foreach ($errors as $message) {
                if (Str::contains($message, $needle) || Str::contains(mb_strtolower($message), mb_strtolower($needle))) {
                    $found = true;

                    break;
                }
            }

            if ($mode === 'any') {
                if ($found) {
                    return;
                }

                $this->fail(
                    'Ningún mensaje de error cita una de las causas '
                    .'("'.implode('" / "', $needles).'"). Mensajes recibidos: '
                    .'['.implode(' | ', $errors).']'.($context ? ' — '.$context : ''),
                );
            }

            $this->assertTrue(
                $found,
                'El motivo de bloqueo debe citar la causa ("'.$needle.'"). '
                .'Mensajes recibidos: ['.implode(' | ', $errors).']'.($context ? ' — '.$context : ''),
            );
        }
    }

    private function assertNothingPersisted(): void
    {
        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }

    /**
     * @param  list<array{product_id: int, quantity: int, usage?: string}>  $items
     */
    private function payload(Project $project, array $items, ?int $regionId = null): array
    {
        return [
            'name' => 'Arquitectura de reglas',
            'region_id' => $regionId ?? $project->region_id,
            'items' => $items,
        ];
    }

    private function regionFor(string $prefix): Region
    {
        $suffix = substr(bin2hex(random_bytes(5)), 0, 6);

        return Region::query()->create([
            'code' => $prefix.'-'.$suffix,
            'name' => 'Región '.$prefix.' ('.$suffix.')',
            'country' => 'ES',
            'is_eu' => true,
        ]);
    }

    private function projectFor(User $user, Region $region, string $maxBudget = '1000.00'): Project
    {
        return Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => $region->id,
            'max_budget' => $maxBudget,
        ]);
    }

    private function productWithTariff(
        Region $region,
        string $name,
        string $type,
        int $priceCents,
        string $pricingModel = 'fijo_mensual',
        ?int $oneTimeCents = null,
        bool $active = true,
        array $extraRegions = [],
        array $attributes = [],
    ): Product {
        $suffix = substr(bin2hex(random_bytes(5)), 0, 6);
        $provider = Provider::query()->firstOrCreate(['name' => 'Proveedor de reglas']);

        $product = Product::query()->create([
            'code' => Str::slug($name).'-'.$suffix,
            'name' => $name,
            'type' => $type,
            'unit_price' => $priceCents / 100,
            'provider_id' => $provider->id,
        ] + ($attributes !== [] ? ['config' => $attributes] : []));

        ProductAvailability::query()->create([
            'product_id' => $product->id,
            'region_id' => $region->id,
            'available' => true,
            'price_cents' => $priceCents,
            'pricing_model' => $pricingModel,
        ] + ($oneTimeCents !== null ? ['one_time_price_cents' => $oneTimeCents] : []));

        foreach ($extraRegions as $extraRegion) {
            ProductAvailability::query()->create([
                'product_id' => $product->id,
                'region_id' => $extraRegion->id,
                'available' => true,
                'price_cents' => $priceCents,
                'pricing_model' => $pricingModel,
            ] + ($oneTimeCents !== null ? ['one_time_price_cents' => $oneTimeCents] : []));
        }

        return $product;
    }

    private function requirementFor(
        Project $project,
        string $field,
        string $operator,
        string $value,
        string $dataType,
        bool $mandatory = true,
    ): Requirement {
        return Requirement::forceCreate([
            'project_id' => $project->id,
            'field_key' => $field,
            'operator' => $operator,
            'value' => $value,
            'data_type' => $dataType,
            'mandatory' => $mandatory,
            'source' => Requirement::SOURCE_MANUAL,
        ]);
    }

    /**
     * La respuesta muestra el importe en euros con dos decimales, el
     * formato ya usado en las pantallas del proyecto (number_format .' €').
     */
    private function assertSeesAmount(TestResponse $response, int $cents): void
    {
        $response->assertSee(number_format($cents / 100, 2).' €', false);
    }
}
