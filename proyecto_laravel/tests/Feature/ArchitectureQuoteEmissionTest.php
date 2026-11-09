<?php

namespace Tests\Feature;

use App\Models\Architecture;
use App\Models\Product;
use App\Models\ProductAvailability;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithArchitectureQuotes;
use Tests\TestCase;

/**
 * feature/architecture-quotes: emisión de presupuestos desde una
 * arquitectura manual (sección 6 de la especificación).
 *
 * Criterios de aceptación cubiertos en este fichero: 5 (consumos inválidos,
 * tarifa ausente o configuración incompleta impiden emitir sin crear
 * registros), 10 (solo el propietario emite y consulta) y la parte del 13
 * relativa a no confiar en datos del navegador.
 *
 * El emisor se observa desde fuera: la acción se localiza en la ficha de la
 * arquitectura, se envía por HTTP y los efectos se comprueban contra las
 * tablas `quotes` y `quote_lines` ya existentes. Nunca se invoca al servicio
 * de emisión internamente ni se presume una URL concreta.
 */
class ArchitectureQuoteEmissionTest extends TestCase
{
    use InteractsWithArchitectureQuotes;
    use RefreshDatabase;

    /**
     * Sección 6: la emisión reutiliza `quotes`/`quote_lines` congelando por
     * elemento nombre, tipo, modalidad, cantidad, precio unitario, subtotal y
     * configuración/consumo; si un elemento tiene coste recurrente y único,
     * se guardan DOS líneas separadas que no mezclan importes.
     *
     * Cálculo exacto (tarifas vigentes del catálogo en el servidor):
     *  - Servidor gerencial (fijo_mensual, 900 ¢, cargo único 1500 ¢, ×2):
     *    línea mensual 900×2 = 1800 ¢ y línea de cargo único 1500×2 = 3000 ¢.
     *  - Balance horaria (por_hora, 25 ¢, 1.5 h, ×3): 25×150×3 = 11250
     *    centésimas de céntimo → 112.5 ¢ redondeado a 113 ¢ (empate arriba).
     *  - Repositorio por GB (por_gb, 10 ¢, 2.5 GB, ×1): 25 ¢ mensual y
     *    cargo único 200 ¢.
     * Cabecera: mensual 1938 ¢ (18.00 + 1.13 + 0.25), único 3200 ¢, total
     * inicial 5138 ¢.
     */
    public function test_emit_freezes_a_snapshot_with_separate_recurring_and_one_time_lines(): void
    {
        $owner = User::factory()->create();
        $region = $this->regionFor('emision');
        $project = $this->projectFor($owner, $region);

        $server = $this->productWithTariff($region, 'Servidor gerencial', 'computacion', 900, 'fijo_mensual', 1500);
        $hours = $this->productWithTariff($region, 'Balance horaria', 'redes', 25, 'por_hora');
        $storage = $this->productWithTariff($region, 'Repositorio por GB', 'bases_de_datos', 10, 'por_gb', 200);

        $architecture = $this->saveManualArchitecture($owner, $project, $region, [
            ['product_id' => $server->id, 'quantity' => 2, 'config' => ['nucleos' => 8]],
            ['product_id' => $hours->id, 'quantity' => 3, 'config' => ['hours_per_month' => 1.5]],
            ['product_id' => $storage->id, 'quantity' => 1, 'config' => ['gb_per_month' => 2.5]],
        ]);

        $this->assertNothingQuoted();

        $this->emitQuoteFrom($owner, $architecture)->assertRedirect();

        $quote = $this->lastQuote();

        // Cabecera congelada: proyecto, propietario, fecha de emisión y subtotales.
        $this->assertSame($project->id, $quote->project_id);
        $this->assertSame($owner->id, $quote->user_id);
        $this->assertNotNull($quote->issued_at);
        $this->assertSame(1938, $quote->subtotal_monthly_cents);
        $this->assertSame(3200, $quote->subtotal_one_time_cents);
        $this->assertSame(5138, $quote->total_cents);

        $lines = $quote->lines()->get();

        $serverLine = $lines->firstOrFail(fn ($line) => $line->product_name === 'Servidor gerencial'
            && $line->unit_price_cents === 900
            && $line->quantity === 2
            && $line->line_total_cents === 1800);
        $this->assertSame('computacion', $serverLine->product_type);

        // El cargo único del MISMO elemento es OTRA línea congelada, con su
        // propio importe (1500×2 = 3000 ¢) y su propio subtotal.
        $oneTimeLines = $lines->filter(fn ($line) => $line->product_name === 'Servidor gerencial'
            && $line->line_total_cents === 3000);
        $this->assertCount(1, $oneTimeLines);
        $this->assertNotSame($serverLine->id, $oneTimeLines->first()->id);
        $this->assertSame(3000, $oneTimeLines->first()->unit_price_cents * $oneTimeLines->first()->quantity);

        // La línea horaria congela el consumo declarado en el elemento.
        $hoursLine = $lines->firstOrFail(fn ($line) => $line->product_name === 'Balance horaria'
            && $line->line_total_cents === 113);
        $this->assertSame(25, $hoursLine->unit_price_cents);
        $this->assertSame(3, $hoursLine->quantity);
        $this->assertEquals(1.5, $hoursLine->config['hours_per_month']);

        $gbLine = $lines->firstOrFail(fn ($line) => $line->product_name === 'Repositorio por GB'
            && $line->line_total_cents === 25);
        $this->assertEquals(2.5, $gbLine->config['gb_per_month']);

        // El elemento con doble cargo queda en exactamente dos líneas
        // (recurrente + único), sin líneas mezcladas ni faltantes.
        $this->assertSame(
            2,
            $lines->filter(fn ($line) => $line->product_name === 'Servidor gerencial')->count(),
        );
    }

    /**
     * Sección 6: tras emitir, la aplicación redirige al detalle del
     * presupuesto existente, que muestra los importes congelados (mensual y
     * pago único separados; total inicial = suma de ambos).
     */
    public function test_emit_redirects_to_the_quote_detail_with_the_frozen_amounts(): void
    {
        $owner = User::factory()->create();
        $region = $this->regionFor('detalle');
        $project = $this->projectFor($owner, $region);

        $server = $this->productWithTariff($region, 'Servidor del detalle', 'computacion', 700);
        $disk = $this->productWithTariff($region, 'Disco del detalle', 'almacenamiento', 400);
        $net = $this->productWithTariff($region, 'Switch del detalle', 'redes', 210, 'fijo_mensual', 130);

        $architecture = $this->saveManualArchitecture($owner, $project, $region, [
            ['product_id' => $server->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $disk->id, 'quantity' => 2, 'config' => []],
            ['product_id' => $net->id, 'quantity' => 4, 'config' => []],
        ]);

        $response = $this->emitQuoteFrom($owner, $architecture)->assertRedirect();

        // 700×1 + 400×2 + 210×4 = 2340 ¢ mensual; único 130×4 = 520 ¢;
        // total 2860 ¢.
        $quote = $this->lastQuote();
        $this->assertSame(2340, $quote->subtotal_monthly_cents);
        $this->assertSame(520, $quote->subtotal_one_time_cents);
        $this->assertSame(2860, $quote->total_cents);

        $location = $response->headers->get('Location');
        $this->assertMatchesRegularExpression(
            '/presupuestos\/\d+/',
            (string) $location,
            'La sección 6 obliga a redirigir al DETALLE del presupuesto emitido.',
        );
        $this->assertMatchesRegularExpression(
            '/presupuestos\/'.$quote->id.'/',
            (string) $location,
            'La redirección debe apuntar al presupuesto recién emitido.',
        );

        $detail = $this->get($location)->assertOk();

        $this->assertSeesAmount($detail, 2860); // total inicial «28.60 €»
        $this->assertSeesAmount($detail, 2340); // mensual «23.40 €/mes»
        $this->assertSeesAmount($detail, 520);  // pago único «5.20 €»
        $detail->assertSee('Servidor del detalle')
            ->assertSee('Disco del detalle')
            ->assertSee('Switch del detalle');
    }

    /**
     * Criterio 7 y sección 6: el aviso de presupuesto (coste inicial sobre
     * max_budget) NO bloquea la emisión: el presupuesto se emite igualmente,
     * con importes correctos, y su estado no se altera por el aviso.
     * Importes: mensual 10.00 €×2 + 5.00 € + 0.10 € = 25.10 €; único
     * 10.50 €×2 + 0.05 € = 21.05 €; coste inicial 46.15 € por encima de los
     * 40.00 € del presupuesto (diferencia 6.15 €).
     */
    public function test_the_budget_warning_does_not_block_emission(): void
    {
        $owner = User::factory()->create();
        $region = $this->regionFor('aviso');
        $project = $this->projectFor($owner, $region, maxBudget: '40.00');

        $server = $this->productWithTariff($region, 'Servidor aviso', 'computacion', 1000, 'fijo_mensual', 1050);
        $disk = $this->productWithTariff($region, 'Disco aviso', 'almacenamiento', 500);
        $net = $this->productWithTariff($region, 'Switch aviso', 'redes', 10, 'fijo_mensual', 5);

        $architecture = $this->saveManualArchitecture($owner, $project, $region, [
            ['product_id' => $server->id, 'quantity' => 2, 'config' => []],
            ['product_id' => $disk->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $net->id, 'quantity' => 1, 'config' => []],
        ]);

        // La ficha desde la que arranca el flujo de emisión muestra el aviso
        // con ambos importes y la diferencia.
        $this->actingAs($owner)->get(route('architectures.show', $architecture))
            ->assertOk()
            ->assertSee('Aviso de presupuesto', false)
            ->assertSee('46.15', false)
            ->assertSee('40.00', false)
            ->assertSee('6.15', false);

        $this->emitQuoteFrom($owner, $architecture)->assertRedirect();

        $quote = $this->lastQuote();
        $this->assertSame(2510, $quote->subtotal_monthly_cents);
        $this->assertSame(2105, $quote->subtotal_one_time_cents);
        $this->assertSame(4615, $quote->total_cents);

        // El aviso no altera el estado del presupuesto emitido.
        $this->assertSame('emitido', $quote->status);
    }

    /**
     * Criterio 5 y sección 4.2: si la tarifa vigente ya no está disponible
     * para algún elemento, la emisión se bloquea con un mensaje concreto
     * (cita el producto causante) y NO se crean registros.
     */
    public function test_emission_blocks_when_a_tariff_becomes_unavailable(): void
    {
        [$owner, $architecture, $disk] = $this->architectureWithThreeElements('no-disponible');

        ProductAvailability::query()
            ->where('product_id', $disk->id)
            ->where('region_id', $architecture->region_id)
            ->update(['available' => false]);

        $this->emitQuoteFrom($owner, $architecture)->assertSessionHasErrors();

        $this->assertMessagesMention(
            $this->allSessionErrors(),
            [$disk->name],
            mode: 'any',
            context: 'tarifa no disponible',
        );
        $this->assertNothingQuoted();
    }

    /**
     * Criterio 5: la fila de tarifa desaparecida también se rechaza antes de
     * persistir nada.
     */
    public function test_emission_blocks_when_a_tariff_row_is_removed(): void
    {
        [$owner, $architecture, $disk] = $this->architectureWithThreeElements('sin-fila');

        ProductAvailability::query()
            ->where('product_id', $disk->id)
            ->where('region_id', $architecture->region_id)
            ->delete();

        $this->emitQuoteFrom($owner, $architecture)->assertSessionHasErrors();

        $this->assertMessagesMention(
            $this->allSessionErrors(),
            [$disk->name],
            mode: 'any',
            context: 'tarifa eliminada',
        );
        $this->assertNothingQuoted();
    }

    /**
     * Criterio 5 y sección 4.1: un consumo inválido (negativo o con más de
     * dos decimales) impide emitir y no crea registros.
     */
    public function test_emission_blocks_invalid_usage_values(): void
    {
        $cases = [
            ['consumo negativo', 'Servidor hora negativa', 'por_hora', ['hours_per_month' => '-5']],
            ['demasiados decimales', 'Servidor hora larga', 'por_hora', ['hours_per_month' => '3.999']],
            ['gb negativo', 'Disco gb negativa', 'por_gb', ['gb_per_month' => '-0.01']],
            ['gb con tres decimales', 'Disco gb larga', 'por_gb', ['gb_per_month' => '2.345']],
        ];

        foreach ($cases as $index => [$context, $name, $model, $config]) {
            $owner = User::factory()->create();
            $region = $this->regionFor('uso-invalido-'.$index);
            $project = $this->projectFor($owner, $region);

            $server = $this->productWithTariff($region, $name, 'computacion', 400, $model);
            $disk = $this->productWithTariff($region, 'Disco de relleno uso', 'almacenamiento', 300);
            $net = $this->productWithTariff($region, 'Switch de relleno uso', 'redes', 200);

            $architecture = $this->saveManualArchitecture($owner, $project, $region, [
                ['product_id' => $server->id, 'quantity' => 1, 'config' => $config],
                ['product_id' => $disk->id, 'quantity' => 1, 'config' => []],
                ['product_id' => $net->id, 'quantity' => 1, 'config' => []],
            ]);

            $this->emitQuoteFrom($owner, $architecture)->assertSessionHasErrors();

            $this->assertMessagesMention(
                $this->allSessionErrors(),
                [$server->name],
                mode: 'any',
                context: $context,
            );
            $this->assertNothingQuoted();
        }
    }

    /**
     * Criterio 5 y sección 4.1: un elemento cuya tarifa del catálogo pasó a
     * `por_hora` DESPUÉS de guardarse no tiene consumo declarado: la
     * configuración incompleta impide emitir.
     */
    public function test_emission_blocks_when_a_saved_element_lacks_usage_for_its_new_pricing_model(): void
    {
        $owner = User::factory()->create();
        $region = $this->regionFor('sin-consumo');
        $project = $this->projectFor($owner, $region);

        $server = $this->productWithTariff($region, 'Servidor reconvertido', 'computacion', 400, 'fijo_mensual');
        $disk = $this->productWithTariff($region, 'Disco reconvertido', 'almacenamiento', 300);
        $net = $this->productWithTariff($region, 'Switch reconvertido', 'redes', 200);

        $architecture = $this->saveManualArchitecture($owner, $project, $region, [
            ['product_id' => $server->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $disk->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $net->id, 'quantity' => 1, 'config' => []],
        ]);

        // La tarifa regional cambia a por_hora cuando la arquitectura ya
        // estaba guardada sin 'hours_per_month'.
        ProductAvailability::query()
            ->where('product_id', $server->id)
            ->where('region_id', $region->id)
            ->update(['pricing_model' => 'por_hora']);

        $this->emitQuoteFrom($owner, $architecture)->assertSessionHasErrors();

        $this->assertMessagesMention(
            $this->allSessionErrors(),
            [$server->name, 'hours_per_month', 'hora'],
            mode: 'any',
            context: 'consumo exigido tras el cambio de tarifa',
        );
        $this->assertNothingQuoted();
    }

    /**
     * Criterio 5 y sección 4.2: una tarifa sin valor válido (precio
     * invalidado en el catálogo tras el guardado) se rechaza con un mensaje
     * concreto y sin crear registros.
     */
    public function test_emission_blocks_when_the_current_price_is_invalid(): void
    {
        $owner = User::factory()->create();
        $region = $this->regionFor('precio-roto');
        $project = $this->projectFor($owner, $region);

        $server = $this->productWithTariff($region, 'Servidor precio roto', 'computacion', 400);
        $disk = $this->productWithTariff($region, 'Disco precio roto', 'almacenamiento', 300);
        $net = $this->productWithTariff($region, 'Switch precio roto', 'redes', 200);

        $architecture = $this->saveManualArchitecture($owner, $project, $region, [
            ['product_id' => $server->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $disk->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $net->id, 'quantity' => 1, 'config' => []],
        ]);

        ProductAvailability::query()
            ->where('product_id', $server->id)
            ->where('region_id', $region->id)
            ->update(['price_cents' => -1]);

        $this->emitQuoteFrom($owner, $architecture)->assertSessionHasErrors();

        $this->assertMessagesMention(
            $this->allSessionErrors(),
            [$server->name],
            mode: 'any',
            context: 'precio inválido',
        );
        $this->assertNothingQuoted();
    }

    /**
     * Criterio 10 y sección 6: solo el propietario del proyecto propietario
     * puede emitir; otro CLIENTE autenticado recibe 403 y no genera nada.
     */
    public function test_a_non_owner_cannot_emit_from_an_architecture(): void
    {
        [$owner, $architecture] = $this->architectureWithThreeElements('ajeno');

        // La acción se localiza como propietario (es el comportamiento
        // observable de la ficha): la protección va en el servidor, no en
        // ocultar el formulario.
        $action = $this->quoteEmissionAction($owner, $architecture);

        $intruder = User::factory()->create();
        $this->projectFor($intruder, $this->regionFor('ajeno-proyecto'));

        $this->postEmissionTo($intruder, $action)->assertForbidden();

        $this->assertNothingQuoted();
    }

    /**
     * Sección 6: el dueño del proyecto solo puede emitir desde una
     * arquitectura manual, no desde otra procedencia como IA.
     */
    public function test_an_owner_cannot_emit_from_a_non_manual_architecture(): void
    {
        [$owner, $architecture] = $this->architectureWithThreeElements('procedencia-ia');
        $action = $this->quoteEmissionAction($owner, $architecture);

        Architecture::query()->whereKey($architecture->id)->update([
            'source' => Architecture::SOURCE_IA,
        ]);

        $this->postEmissionTo($owner, $action)->assertForbidden();
        $this->assertNothingQuoted();
    }

    /**
     * La autorización de CLIENTE se aplica además de la propiedad: una
     * cuenta de otro rol no ve el formulario y una acción ya conocida se
     * rechaza en el servidor.
     */
    public function test_only_a_cliente_owner_can_emit_from_an_architecture(): void
    {
        [$owner, $architecture] = $this->architectureWithThreeElements('rol-no-cliente');
        $action = $this->quoteEmissionAction($owner, $architecture);

        $owner->forceFill(['role' => 'arquitecto'])->save();
        $owner->refresh();

        $detail = $this->actingAs($owner)
            ->get(route('architectures.show', $architecture))
            ->assertOk();
        $detail->assertDontSee('Emitir presupuesto desde esta arquitectura');

        $this->postEmissionTo($owner, $action)->assertForbidden();
        $this->assertNothingQuoted();
    }

    /**
     * Criterio 10: la acción también exige sesión para quien solo conoce la
     * URL del formulario.
     */
    public function test_a_guest_cannot_emit_from_an_architecture(): void
    {
        [$owner, $architecture] = $this->architectureWithThreeElements('invitado');

        $this->emitQuoteAsGuest($owner, $architecture)->assertRedirect('acceso');

        $this->assertNothingQuoted();
    }

    /**
     * Criterio 10 y sección 6: «perteneciente a uno de sus proyectos no
     * eliminados». Si el proyecto propietario se elimina, la cuenta incluso
     * del propietario se bloquea y no se emiten presupuestos ni líneas.
     */
    public function test_emission_rejects_an_architecture_of_a_trashed_project(): void
    {
        [$owner, $architecture, , $project] = $this->architectureWithThreeElements('proyecto-borrado');

        // La acción se descubre mientras el proyecto está vivo; la petición
        // se envía DESPUÉS de eliminarlo: el servidor no debe emitir
        // presupuestos para arquitecturas de proyectos no existentes.
        $action = $this->quoteEmissionAction($owner, $architecture);

        $project->delete();

        $this->postEmissionTo($owner, $action)->assertForbidden();

        $this->assertNothingQuoted();
    }

    /**
     * Criterio 10 y sección 7: la autorización se resuelve por el proyecto
     * propietario y el cálculo por la tarifa vigente del catálogo; los
     * valores de `user_id`, `project_id`, `region_id`, precios o totales
     * que un navegador malicioso pudiera enviar se ignoran.
     */
    public function test_posted_user_project_region_and_price_fields_are_not_trusted(): void
    {
        $owner = User::factory()->create();
        $region = $this->regionFor('no-confiar');
        $project = $this->projectFor($owner, $region);

        $server = $this->productWithTariff($region, 'Servidor fiel', 'computacion', 900, 'fijo_mensual', 1500);
        $disk = $this->productWithTariff($region, 'Disco fiel', 'almacenamiento', 400);
        $net = $this->productWithTariff($region, 'Switch fiel', 'redes', 200);

        $architecture = $this->saveManualArchitecture($owner, $project, $region, [
            ['product_id' => $server->id, 'quantity' => 2, 'config' => []],
            ['product_id' => $disk->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $net->id, 'quantity' => 1, 'config' => []],
        ]);

        $thief = User::factory()->create();
        $stolenProject = $this->projectFor($thief, $this->regionFor('robada'), maxBudget: '1.00');
        $otherRegion = $this->regionFor('inventada');

        $this->emitQuoteFrom($owner, $architecture, [
            'user_id' => $thief->id,
            'project_id' => $stolenProject->id,
            'region_id' => $otherRegion->id,
            'price_cents' => 7777,
            'unit_price_cents' => 7777,
            'total_cents' => 77,
            'subtotal_monthly_cents' => 77,
            'subtotal_one_time_cents' => 77,
        ])->assertRedirect();

        $quote = $this->lastQuote();

        // Atribución real: proyecto y usuario propietarios; importes del
        // catálogo (900×2 + 400 + 200 = 2400 ¢ mensual; único 1500×2 =
        // 3000 ¢; total 5400 ¢), nunca los valores enviados.
        $this->assertSame($owner->id, $quote->user_id);
        $this->assertSame($project->id, $quote->project_id);
        $this->assertSame(2400, $quote->subtotal_monthly_cents);
        $this->assertSame(3000, $quote->subtotal_one_time_cents);
        $this->assertSame(5400, $quote->total_cents);

        $quote->lines->each(function ($line) use ($server, $disk, $net): void {
            $this->assertContains(
                $line->product_name,
                [$server->name, $disk->name, $net->name],
                'El presupuesto solo puede contener los elementos de la arquitectura '
                .'propietaria, con precios recalculados del catálogo.',
            );
        });
    }

    /**
     * Criterio 10 (consulta): después de emitir, la consulta del detalle es
     * únicamente del propietario de la arquitectura; otro CLIENTE que conozca
     * la URL llega a 403.
     */
    public function test_only_the_owner_can_read_the_emitted_quote_detail(): void
    {
        [$owner, $architecture] = $this->architectureWithThreeElements('privado');

        $this->emitQuoteFrom($owner, $architecture)->assertRedirect();

        $detailUrl = route('quotes.show', $this->lastQuote());

        $this->actingAs($owner)->get($detailUrl)->assertOk();

        $intruder = User::factory()->create();
        $this->actingAs($intruder)->get($detailUrl)->assertForbidden();
    }

    /**
     * Arquitectura válida de tres elementos reutilizable por varios casos.
     *
     * @return array{0: User, 1: Architecture, 2: Product, 3: Project}
     */
    private function architectureWithThreeElements(string $suffix): array
    {
        $owner = User::factory()->create();
        $region = $this->regionFor($suffix);
        $project = $this->projectFor($owner, $region);

        $server = $this->productWithTariff($region, 'Servidor '.$suffix, 'computacion', 500);
        $disk = $this->productWithTariff($region, 'Disco '.$suffix, 'almacenamiento', 300);
        $net = $this->productWithTariff($region, 'Switch '.$suffix, 'redes', 200);

        $architecture = $this->saveManualArchitecture($owner, $project, $region, [
            ['product_id' => $server->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $disk->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $net->id, 'quantity' => 1, 'config' => []],
        ]);

        return [$owner, $architecture, $server, $project];
    }
}
