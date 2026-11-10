<?php

namespace Tests\Feature;

use App\Models\Architecture;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductAvailability;
use App\Models\Project;
use App\Models\Provider;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithArchitectureQuotes;
use Tests\TestCase;

/**
 * feature/architecture-pricing: cálculo de precios, consumos y cargo
 * único de una arquitectura manual (criterios de aceptación 1 a 5 y 13).
 *
 * El desglose se observa en la misma pantalla de revisión
 * (paso "revisar" → página de comprobación) y en el detalle de la
 * arquitectura guardada: importes concretos calculados en el servidor a
 * partir de la tarifa regional del catálogo, nunca de datos enviados
 * por el navegador.
 *
 * Fórmulas (sección 4.1 de la especificación):
 * - fijo_mensual: price_cents × quantity.
 * - por_hora: price_cents × hours_per_month × quantity (consumo obligatorio).
 * - por_gb: price_cents × gb_per_month × quantity (consumo obligatorio).
 * - Cargo único: one_time_price_cents × quantity, siempre separado del recurrente.
 * - Coste inicial: subtotal mensual de un mes + subtotal de cargos únicos.
 */
class ArchitecturePricingTest extends TestCase
{
    use InteractsWithArchitectureQuotes, RefreshDatabase;

    /**
     * Criterio 1: una tarifa fijo_mensual calcula exactamente
     * precio × cantidad en el subtotal mensual de cada línea y del
     * resumen, sin requerir consumos.
     */
    public function test_fijo_mensual_calculates_price_times_quantity_in_the_monthly_subtotal(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('fijo');
        $project = $this->projectFor($user, $region);

        // 1250 × 3 = 3750 céntimos
        $compute = $this->productWithTariff($region, 'Servidor fijo', 'computacion', 1250, 'fijo_mensual');
        // 990 × 2 = 1980 céntimos
        $storage = $this->productWithTariff($region, 'Disco fijo', 'almacenamiento', 990, 'fijo_mensual');
        // 1800 × 2 = 3600 céntimos
        $network = $this->productWithTariff($region, 'Firewall fijo', 'redes', 1800, 'fijo_mensual');

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $compute->id, 'quantity' => 3],
                ['product_id' => $storage->id, 'quantity' => 2],
                ['product_id' => $network->id, 'quantity' => 2],
            ]))
            ->assertSessionHasNoErrors();

        $check = $this->get(route('projects.architectures.check', $project))
            ->assertOk();

        // Por línea: 3750, 1980 y 3600 céntimos.
        $this->assertSeesAmount($check, 3750);
        $this->assertSeesAmount($check, 1980);
        $this->assertSeesAmount($check, 3600);

        // Subtotal mensual (9330) y coste inicial (mensual + único: 0).
        $this->assertSeesAmount($check, 9330);

        // Criterio 10: la previsualización ya no muestra columna de
        // configuración (el consumo se muestra en «Consumo mensual»).
        $this->assertDontSee('Configuración', $check);

        $response = $this->post(route('projects.architectures.store', $project));
        $architecture = Architecture::query()->firstOrFail();
        $response->assertRedirect(route('architectures.show', $architecture));

        // El detalle de la arquitectura guardada muestra el mismo desglose.
        $detail = $this->get(route('architectures.show', $architecture))->assertOk();
        $this->assertSeesAmount($detail, 3750);
        $this->assertSeesAmount($detail, 1980);
        $this->assertSeesAmount($detail, 3600);
        $this->assertSeesAmount($detail, 9330);
        $this->assertDontSee('Configuración', $detail);
    }

    /**
     * Criterio 7: una tarifa fijo_mensual ignora el consumo mensual
     * enviado por el navegador (no produce consumo interno, ni precio
     * extra, ni error).
     */
    public function test_fijo_mensual_ignores_the_usage_sent_by_the_client(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('fijo-uso');
        $project = $this->projectFor($user, $region);

        // 1000 × 2 = 2000 céntimos; la tarifa fija cobra igual con o sin uso.
        $compute = $this->productWithTariff($region, 'Servidor indiferente', 'computacion', 1000);
        $storage = $this->productWithTariff($region, 'Disco indiferente', 'almacenamiento', 500);
        $network = $this->productWithTariff($region, 'Firewall indiferente', 'redes', 300);

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $compute->id, 'quantity' => 2, 'usage' => '12.75'],
                ['product_id' => $storage->id, 'quantity' => 1, 'usage' => '0'],
                ['product_id' => $network->id, 'quantity' => 1, 'usage' => '999999.99'],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSeesAmount($this->get(route('projects.architectures.check', $project)), 2000);

        $this->post(route('projects.architectures.store', $project));
        $architecture = Architecture::query()->where('project_id', $project->id)->firstOrFail();

        foreach ($architecture->items as $item) {
            $this->assertSame(
                [],
                $item->config,
                'Las tarifas fijas se guardan sin consumo interno.',
            );
        }
    }

    /**
     * Criterio 5: el consumo mensual indicado por el formulario
     * (`items[*][usage]`) exige la tarifa por_hora y el ejemplo se
     * calcula exactamente (200 céntimos/hora × 7.5 horas × 4 = 6000
     * céntimos = 60.00 €). El servidor construye el consumo interno.
     */
    public function test_por_hora_derives_hours_per_month_from_the_usage_field(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('hora');
        $project = $this->projectFor($user, $region);

        // 200 × 7.5 × 4 = 6000 céntimos
        $lambda = $this->productWithTariff($region, 'Lambda por hora', 'almacenamiento', 200, 'por_hora');
        // Fijas de relleno: 850 × 2 = 1700 y 999 × 3 = 2997.
        $compute = $this->productWithTariff($region, 'Servidor fijo', 'computacion', 850, 'fijo_mensual');
        $network = $this->productWithTariff($region, 'Firewall fijo', 'redes', 999, 'fijo_mensual');

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $lambda->id, 'quantity' => 4, 'usage' => '7.5'],
                ['product_id' => $compute->id, 'quantity' => 2],
                ['product_id' => $network->id, 'quantity' => 3],
            ]))
            ->assertSessionHasNoErrors();

        $check = $this->get(route('projects.architectures.check', $project))->assertOk();

        // Línea por_hora: 6000 céntimos.
        $this->assertSeesAmount($check, 6000);

        // Subtotal mensual: 6000 + 1700 + 2997 = 10697 céntimos.
        $this->assertSeesAmount($check, 10697);

        // El consumo mensual queda persistido con el valor numérico.
        $this->post(route('projects.architectures.store', $project));
        $architecture = Architecture::query()->firstOrFail();
        $stored = $architecture->items()->where('product_id', $lambda->id)->firstOrFail();

        $this->assertArrayHasKey('hours_per_month', $stored->config);
        // Acepta 7.5 como número o como texto "7.5": el contrato es el valor numérico.
        $this->assertSame(7.5, (float) $stored->config['hours_per_month']);
    }

    /**
     * Criterio 6: el consumo mensual (`items[*][usage]`) sobre una
     * tarifa por_gb se calcula exactamente (15 céntimos/GB × 120 GB × 2
     * = 3600 céntimos = 36.00 €).
     */
    public function test_por_gb_uses_gb_per_month_and_calculates_the_exact_example(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('giga');
        $project = $this->projectFor($user, $region);

        // 15 × 120 × 2 = 3600 céntimos
        $transfer = $this->productWithTariff($region, 'Tráfico por GB', 'redes', 15, 'por_gb');
        // Fijas de relleno: 777 × 2 = 1554 y 499 × 3 = 1497.
        $compute = $this->productWithTariff($region, 'Servidor fijo', 'computacion', 777, 'fijo_mensual');
        $storage = $this->productWithTariff($region, 'Disco fijo', 'almacenamiento', 499, 'fijo_mensual');

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $transfer->id, 'quantity' => 2, 'usage' => '120'],
                ['product_id' => $compute->id, 'quantity' => 2],
                ['product_id' => $storage->id, 'quantity' => 3],
            ]))
            ->assertSessionHasNoErrors();

        $check = $this->get(route('projects.architectures.check', $project))->assertOk();

        // Línea por_gb: 3600 céntimos.
        $this->assertSeesAmount($check, 3600);

        // Subtotal mensual: 3600 + 1554 + 1497 = 6651 céntimos.
        $this->assertSeesAmount($check, 6651);

        // El consumo mensual queda persistido con el valor numérico.
        $this->post(route('projects.architectures.store', $project));
        $architecture = Architecture::query()->firstOrFail();
        $stored = $architecture->items()->where('product_id', $transfer->id)->firstOrFail();

        $this->assertArrayHasKey('gb_per_month', $stored->config);
        $this->assertSame(120.0, (float) $stored->config['gb_per_month']);
    }

    /**
     * Criterio 5, caso límite: el consumo obligatorio debe ser MAYOR
     * que cero. Un `usage` de 0 para una tarifa por_hora equivale a no
     * indicarlo: bloquea la revisión citando el elemento y la unidad, y
     * no persiste nada.
     */
    public function test_zero_usage_for_a_metered_tariff_is_rejected(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('cero');
        $project = $this->projectFor($user, $region);

        $lambda = $this->productWithTariff($region, 'Flota apagada', 'almacenamiento', 200, 'por_hora');
        $compute = $this->productWithTariff($region, 'Servidor fijo', 'computacion', 111, 'fijo_mensual');
        $network = $this->productWithTariff($region, 'Firewall fijo', 'redes', 500, 'fijo_mensual');

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $lambda->id, 'quantity' => 3, 'usage' => '0'],
                ['product_id' => $compute->id, 'quantity' => 2],
                ['product_id' => $network->id, 'quantity' => 1],
            ]))
            ->assertSessionHasErrors();

        $this->assertMessagesMention(
            $this->allSessionErrors(),
            [$lambda->name, 'hora'],
            mode: 'all',
            context: 'consumo cero en por_hora',
        );

        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }

    /**
     * Criterio 4: el cargo único se conserva separado del recurrente y
     * el coste inicial suma ambos importes. Un mismo elemento puede
     * tener tarifa fija y cargo único a la vez.
     */
    public function test_one_time_charge_is_kept_separate_and_initial_cost_adds_it_to_the_monthly_subtotal(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('unico');
        $project = $this->projectFor($user, $region);

        // Mensual: 1000 × 2 = 2000. Cargo único: 4800 × 2 = 9600.
        $compute = $this->productWithTariff($region, 'Servidor con setup', 'computacion', 1000, 'fijo_mensual', 4800);
        // 900 × 3 = 2700.
        $storage = $this->productWithTariff($region, 'Disco fijo', 'almacenamiento', 900, 'fijo_mensual');
        // 500 × 1 = 500.
        $network = $this->productWithTariff($region, 'Firewall fijo', 'redes', 500, 'fijo_mensual');

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $compute->id, 'quantity' => 2],
                ['product_id' => $storage->id, 'quantity' => 3],
                ['product_id' => $network->id, 'quantity' => 1],
            ]))
            ->assertSessionHasNoErrors();

        $check = $this->get(route('projects.architectures.check', $project))->assertOk();

        // Mensual por línea y del elemento: 2000 y 2700; cargo único del elemento: 9600.
        $this->assertSeesAmount($check, 2000);
        $this->assertSeesAmount($check, 2700);
        $this->assertSeesAmount($check, 9600);

        // Subtotal mensual 5200, pago único 9600 y coste inicial 14800: triple separación.
        $this->assertSeesAmount($check, 5200);
        $this->assertSeesAmount($check, 14800);

        // El detalle de la arquitectura guardada repite el desglose.
        $this->post(route('projects.architectures.store', $project));
        $architecture = Architecture::query()->firstOrFail();
        $detail = $this->get(route('architectures.show', $architecture))->assertOk();

        $this->assertSeesAmount($detail, 9600);
        $this->assertSeesAmount($detail, 5200);
        $this->assertSeesAmount($detail, 14800);
    }

    /**
     * Los importes no definidos para el catálogo de ejemplo no se inventan:
     * todos los productos sembrados comienzan con cargo único de cero céntimos.
     */
    public function test_existing_catalog_seed_data_starts_with_zero_one_time_charges(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertGreaterThan(0, ProductAvailability::query()->count());
        $this->assertSame(
            0,
            ProductAvailability::query()->where('one_time_price_cents', '>', 0)->count(),
        );
    }

    /**
     * Criterio 5: el consumo obligatorio ausente (por_hora, sin el
     * campo «Consumo mensual») bloquea la revisión y no persiste nada.
     */
    public function test_missing_hours_per_month_blocks_review_and_persists_nothing(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('sin-horas');
        $project = $this->projectFor($user, $region);

        $lambda = $this->productWithTariff($region, 'Función elástica', 'almacenamiento', 200, 'por_hora');
        $compute = $this->productWithTariff($region, 'Servidor fijo', 'computacion', 850, 'fijo_mensual');
        $network = $this->productWithTariff($region, 'Firewall fijo', 'redes', 999, 'fijo_mensual');

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $lambda->id, 'quantity' => 4],
                ['product_id' => $compute->id, 'quantity' => 2],
                ['product_id' => $network->id, 'quantity' => 3],
            ]))
            ->assertSessionHasErrors();

        // Criterio 5: el error identifica el elemento y la unidad
        // (nombre del producto + «hora»), no un mensaje genérico.
        $this->assertMessagesMention(
            $this->allSessionErrors(),
            [$lambda->name, 'hora'],
            mode: 'all',
            context: 'por_hora sin consumo (criterio 5)',
        );

        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }

    /**
     * Criterio 5: el consumo obligatorio ausente (por_gb sin «Consumo
     * mensual») bloquea la revisión citando la unidad, y no persiste
     * nada.
     */
    public function test_missing_gb_per_month_blocks_review_and_persists_nothing(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('sin-gb');
        $project = $this->projectFor($user, $region);

        $transfer = $this->productWithTariff($region, 'Transferencia vacía', 'redes', 15, 'por_gb');
        $compute = $this->productWithTariff($region, 'Servidor fijo', 'computacion', 850, 'fijo_mensual');
        $storage = $this->productWithTariff($region, 'Disco fijo', 'almacenamiento', 499, 'fijo_mensual');

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $transfer->id, 'quantity' => 2],
                ['product_id' => $compute->id, 'quantity' => 2],
                ['product_id' => $storage->id, 'quantity' => 3],
            ]))
            ->assertSessionHasErrors();

        $this->assertMessagesMention(
            $this->allSessionErrors(),
            [$transfer->name, 'GB'],
            mode: 'all',
            context: 'por_gb sin consumo',
        );

        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }

    /**
     * Criterio 5 y contrato 4: los valores de «Consumo mensual»
     * negativos, no numéricos, demasiado grandes o con más de dos
     * decimales se rechazan y no se persiste nada.
     */
    public function test_invalid_usage_values_are_rejected_and_persist_nothing(): void
    {
        // Valores tal y como llegan del formulario (texto del input).
        $cases = ['-3', '1.234', '99999999999999999', 'mucho', ''];

        foreach ($cases as $i => $usage) {
            $user = User::factory()->create();
            $region = $this->regionFor('uso-'.$i);
            $project = $this->projectFor($user, $region);

            // Mitad de los casos con tarifa por_hora y mitad con por_gb:
            // el rechazo de un consumo inválido no depende de la unidad.
            $metered = $i < 3 ? 'por_hora' : 'por_gb';
            $consumable = $this->productWithTariff($region, 'Recurso tarificado', $i < 3 ? 'almacenamiento' : 'redes', 200, $metered);
            $filler1 = $this->productWithTariff($region, 'Servidor fijo', 'computacion', 850, 'fijo_mensual');
            $filler2 = $this->productWithTariff($region, 'Firewall fijo', 'redes', 999, 'fijo_mensual');

            $row = ['product_id' => $consumable->id, 'quantity' => 1];

            if (! in_array($usage, ['', null], true)) {
                $row['usage'] = $usage;
            }

            $this->actingAs($user)
                ->post(route('projects.architectures.review', $project), $this->payload($project, [
                    $row,
                    ['product_id' => $filler1->id, 'quantity' => 2],
                    ['product_id' => $filler2->id, 'quantity' => 1],
                ]))
                ->assertSessionHasErrors();

            $this->assertDatabaseCount('architectures', 0);
            $this->assertDatabaseCount('architecture_items', 0);
        }
    }

    /**
     * Criterio 5, sección 4.2: una disponibilidad cuya tarifa declarada
     * no es ninguno de los tres modelos vigentes del catálogo
     * ('mensual' no existe en el enum PricingModel) se rechaza con un
     * error concreto; no se persiste nada ni se asume una tarifa.
     */
    public function test_availability_with_an_invalid_pricing_model_rejects_pricing_and_persists_nothing(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('tarifa-antigua');
        $project = $this->projectFor($user, $region);

        // 'mensual' es el valor por defecto de filas históricas de
        // product_availabilities y NO es un caso del enum PricingModel.
        $legacy = $this->productWithTariff($region, 'Disco histórico', 'almacenamiento', 990, 'mensual');
        $compute = $this->productWithTariff($region, 'Servidor fijo', 'computacion', 850, 'fijo_mensual');
        $network = $this->productWithTariff($region, 'Firewall fijo', 'redes', 999, 'fijo_mensual');

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $legacy->id, 'quantity' => 2],
                ['product_id' => $compute->id, 'quantity' => 2],
                ['product_id' => $network->id, 'quantity' => 3],
            ]))
            ->assertSessionHasErrors(['items']);

        $this->assertDatabaseCount('architectures', 0);
        $this->assertDatabaseCount('architecture_items', 0);
    }

    /**
     * Sección 4.2: cuando el uso fraccionario produce fracción de
     * céntimo se redondea una sola vez por línea al céntimo más
     * próximo con empate hacia arriba: 105 céntimos × 0.5 horas × 1 =
     * 52.5 → 53 céntimos (0.53 €). El redondeo bancario daría 52
     * (16.06 € en el subtotal) y el redondeo a la baja también.
     */
    public function test_fractional_cents_round_half_up_once_per_line(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('redondeo');
        $project = $this->projectFor($user, $region);

        // 105 × 0.5 × 1 = 52.5 céntimos → 53 (empate hacia arriba).
        $lambda = $this->productWithTariff($region, 'Lambda media hora', 'almacenamiento', 105, 'por_hora');
        // Fijas de relleno: 333 × 2 = 666 y 444 × 2 = 888.
        $compute = $this->productWithTariff($region, 'Servidor fijo', 'computacion', 333, 'fijo_mensual');
        $network = $this->productWithTariff($region, 'Firewall fijo', 'redes', 444, 'fijo_mensual');

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $lambda->id, 'quantity' => 1, 'usage' => '0.5'],
                ['product_id' => $compute->id, 'quantity' => 2],
                ['product_id' => $network->id, 'quantity' => 2],
            ]))
            ->assertSessionHasNoErrors();

        $check = $this->get(route('projects.architectures.check', $project))->assertOk();

        // Línea redondeada una vez: 0.53 €.
        $this->assertSeesAmount($check, 53);

        // Subtotal mensual: 53 + 666 + 888 = 1607 céntimos ("16.07 €"),
        // nunca el redondeo bancario (1606) ni el a la baja (1605).
        $this->assertSeesAmount($check, 1607);
        $this->assertNotSeesAmount($check, 1606);
    }

    /**
     * Sección 4.2 y 4.3: toda tarifa se vuelve a leer del catálogo en el
     * servidor; un cambio de tarifa entre revisiones provoca un
     * recálculo y el importe antiguo desaparece del desglose.
     */
    public function test_a_tariff_change_causes_server_side_recalculation(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('recalculo');
        $project = $this->projectFor($user, $region);

        // 1000 × 2 = 2000; fijas de relleno 500 y 300. Mensual: 2800.
        $compute = $this->productWithTariff($region, 'Servidor fijo', 'computacion', 1000, 'fijo_mensual');
        $storage = $this->productWithTariff($region, 'Disco fijo', 'almacenamiento', 500, 'fijo_mensual');
        $network = $this->productWithTariff($region, 'Firewall fijo', 'redes', 300, 'fijo_mensual');

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $compute->id, 'quantity' => 2],
                ['product_id' => $storage->id, 'quantity' => 1],
                ['product_id' => $network->id, 'quantity' => 1],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSeesAmount($this->get(route('projects.architectures.check', $project)), 2000);

        // El proveedor sube la tarifa regional: 3500 × 2 = 7000.
        $compute->regions()->updateExistingPivot($region->id, [
            'price_cents' => 3500,
        ]);

        $recalculated = $this->get(route('projects.architectures.check', $project))->assertOk();
        $this->assertSeesAmount($recalculated, 7000);
        $this->assertNotSeesAmount($recalculated, 2000);
    }

    /**
     * Sección 7: no se confía en total, subtotales ni precios enviados
     * por el navegador; el desglose mostrado procede solo del catálogo.
     */
    public function test_totals_and_prices_sent_by_the_client_are_not_trusted(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('no-confiar');
        $project = $this->projectFor($user, $region);

        // 1000 × 2 = 2000; 500 y 300 de relleno. Mensual real: 2800 (28.00 €).
        $compute = $this->productWithTariff($region, 'Servidor fijo', 'computacion', 1000, 'fijo_mensual');
        $storage = $this->productWithTariff($region, 'Disco fijo', 'almacenamiento', 500, 'fijo_mensual');
        $network = $this->productWithTariff($region, 'Firewall fijo', 'redes', 300, 'fijo_mensual');

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $compute->id, 'quantity' => 2],
                ['product_id' => $storage->id, 'quantity' => 1],
                ['product_id' => $network->id, 'quantity' => 1],
            ], extra: [
                // Un navegador manipulado intenta colar importes oficiales.
                'total' => 9999,
                'subtotal_mensual_cents' => 9999,
                'prices' => [$compute->id => 1],
            ]))
            ->assertSessionHasNoErrors();

        $check = $this->get(route('projects.architectures.check', $project))->assertOk();

        // Solo aparecen los importes calculados con la BD:
        // línea 2000, mensual 2800; jamás el total falsificado (99.99 €).
        $this->assertSeesAmount($check, 2000);
        $this->assertSeesAmount($check, 2800);
        $this->assertNotSeesAmount($check, 9999);
    }

    /**
     * Criterio 13: el cálculo y el desglose de arquitecturas no tocan el
     * carrito, no emiten presupuestos ni crean arquitecturas de IA; el
     * flujo completo funciona sin carrito y con un carrito ajeno intacto.
     */
    public function test_pricing_flow_leaves_cart_quotes_and_ai_sources_untouched(): void
    {
        $user = User::factory()->create();
        $region = $this->regionFor('aislada');
        $project = $this->projectFor($user, $region);

        $compute = $this->productWithTariff($region, 'Servidor fijo', 'computacion', 1000, 'fijo_mensual');
        $storage = $this->productWithTariff($region, 'Disco fijo', 'almacenamiento', 900, 'fijo_mensual');
        $network = $this->productWithTariff($region, 'Firewall fijo', 'redes', 700, 'fijo_mensual');

        // Carrito previo con contenido: la nueva funcionalidad no debe
        // leerlo, vaciarlo ni modificarlo.
        CartItem::forceCreate([
            'user_id' => $user->id,
            'product_id' => $compute->id,
            'quantity' => 7,
            'config' => ['region_id' => $region->id],
        ]);

        $this->actingAs($user)
            ->post(route('projects.architectures.review', $project), $this->payload($project, [
                ['product_id' => $compute->id, 'quantity' => 2],
                ['product_id' => $storage->id, 'quantity' => 3],
                ['product_id' => $network->id, 'quantity' => 1],
            ]))
            ->assertSessionHasNoErrors();

        $this->get(route('projects.architectures.check', $project))->assertOk();
        $this->post(route('projects.architectures.store', $project));
        $architecture = Architecture::query()->firstOrFail();
        $this->get(route('architectures.show', $architecture))->assertOk();

        // El carrito sigue exactamente igual (mismo contendido, no vaciado).
        $cart = CartItem::query()->get();
        $this->assertCount(1, $cart);
        $this->assertSame($compute->id, $cart[0]->product_id);
        $this->assertSame(7, $cart[0]->quantity);

        // Ningún presupuesto se emitió como efecto del cálculo.
        $this->assertDatabaseCount('quotes', 0);
        $this->assertDatabaseCount('quote_lines', 0);

        // La arquitectura guardada es manual: la funcionalidad de precios
        // no invoca IA ni fabrica arquitecturas de esa procedencia.
        $this->assertSame(0, Architecture::query()->where('source', Architecture::SOURCE_IA)->count());
        $this->assertSame(1, Architecture::query()->where('source', Architecture::SOURCE_MANUAL)->count());
    }

    /**
     * Fila de formulario tal y como la envía el navegador con valores
     * de consumo como texto (los consumos los normaliza el servidor).
     *
     * @param  array<int, array{product_id: int, quantity: int|string, usage?: string}>  $items
     * @param  array<string, mixed>  $extra
     */
    private function payload(Project $project, array $items, array $extra = []): array
    {
        return array_merge([
            'name' => 'Arquitectura de precios',
            'region_id' => $project->region_id,
            'items' => $items,
        ], $extra);
    }

    private function regionFor(string $prefix): Region
    {
        $suffix = substr(bin2hex(random_bytes(5)), 0, 6);

        return Region::query()->create([
            'code' => $prefix.'-'.$suffix,
            'name' => 'Región de '.$prefix.' ('.$suffix.')',
            'country' => 'ES',
            'is_eu' => true,
        ]);
    }

    private function projectFor(User $user, Region $region): Project
    {
        return Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => $region->id,
        ]);
    }

    /**
     * Producto activo con una tarifa regional concreta, tal y como el
     * formulario ofrece al CLIENTE. `oneTimeCents` simula el dato de
     * coste único que añadirá feature/architecture-pricing a la
     * disponibilidad regional.
     */
    private function productWithTariff(
        Region $region,
        string $name,
        string $type,
        int $priceCents,
        string $pricingModel,
        ?int $oneTimeCents = null,
    ): Product {
        $suffix = substr(bin2hex(random_bytes(5)), 0, 6);
        $provider = Provider::query()->firstOrCreate(['name' => 'TarifasTest '.$suffix]);

        $product = Product::query()->create([
            'code' => Str::slug($name).'-'.$suffix,
            'name' => $name,
            'type' => $type,
            'unit_price' => $priceCents / 100,
            'provider_id' => $provider->id,
            'active' => true,
        ]);

        $pivot = [
            'provider_id' => $provider->id,
            'available' => true,
            'price_cents' => $priceCents,
            'pricing_model' => $pricingModel,
        ];

        if ($oneTimeCents !== null) {
            $pivot['one_time_price_cents'] = $oneTimeCents;
        }

        $product->regions()->attach($region->id, $pivot);

        return $product;
    }

    /**
     * La respuesta muestra el importe en euros con dos decimales, el
     * formato ya usado en las pantallas del proyecto (number_format .' €').
     */
    private function assertSeesAmount(TestResponse $response, int $cents): void
    {
        $response->assertSee(number_format($cents / 100, 2).' €', false);
    }

    private function assertNotSeesAmount(TestResponse $response, int $cents): void
    {
        $response->assertDontSee(number_format($cents / 100, 2).' €', false);
    }
}
