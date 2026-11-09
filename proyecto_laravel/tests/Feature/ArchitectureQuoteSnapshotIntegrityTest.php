<?php

namespace Tests\Feature;

use App\Models\Architecture;
use App\Models\ArchitectureItem;
use App\Models\CartItem;
use App\Models\ProductAvailability;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithArchitectureQuotes;
use Tests\TestCase;

/**
 * feature/architecture-quotes: integridad del snapshot emitido desde una
 * arquitectura manual (sección 6 de la especificación).
 *
 * Criterios de aceptación cubiertos aquí: 11 (transacción única con
 * rollback en fallos controlados), 12 (los cambios posteriores del
 * catálogo no alteran el presupuesto histórico) y 13 (la nueva
 * funcionalidad no altera el carrito ni genera artefactos de IA).
 *
 * Las mutaciones del catálogo y del carrito se realizan con los modelos ya
 * existentes: el servicio de emisión se observa únicamente por su efecto
 * HTTP y por las filas persistidas de `quotes` y `quote_lines`.
 */
class ArchitectureQuoteSnapshotIntegrityTest extends TestCase
{
    use InteractsWithArchitectureQuotes;
    use RefreshDatabase;

    /**
     * Criterio 11 y sección 6: crear cabecera y líneas es UNA transacción.
     * Un fallo controlado de escritura (una línea que no se puede guardar)
     * revierte TODO: ni cabecera parcial ni líneas parciales.
     */
    public function test_a_controlled_write_failure_rolls_back_header_and_lines(): void
    {
        $owner = User::factory()->create();
        $region = $this->regionFor('rollback');
        $project = $this->projectFor($owner, $region);

        // Elementos con cargos recurrentes y únicos: la emisión escribiría
        // una cabecera y varias líneas antes del fallo controlado.
        $server = $this->productWithTariff($region, 'Servidor rollback', 'computacion', 900, 'fijo_mensual', 1000);
        $disk = $this->productWithTariff($region, 'Disco detonante', 'almacenamiento', 500);
        $net = $this->productWithTariff($region, 'Switch rollback', 'redes', 200);

        $architecture = $this->saveManualArchitecture($owner, $project, $region, [
            ['product_id' => $server->id, 'quantity' => 2, 'config' => []],
            ['product_id' => $disk->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $net->id, 'quantity' => 1, 'config' => []],
        ]);

        QuoteLine::creating(function (QuoteLine $line): void {
            if ($line->product_name === 'Disco detonante') {
                throw new \RuntimeException('Fallo controlado de escritura de la línea del presupuesto.');
            }
        });

        $this->emitQuoteFrom($owner, $architecture);

        // Ningún dato parcial: ni cabecera ni líneas (rollback total).
        $this->assertNothingQuoted();
    }

    /**
     * Criterio 12 y sección 6: los cambios posteriores de NOMENCLATURA,
     * tarifa, tipo o disponibilidad del catálogo no alteran el presupuesto
     * emitido: los importes, nombres, tipos y configuraciones congelados se
     * mantienen en la base de datos.
     */
    public function test_later_catalog_changes_do_not_touch_the_emitted_quote(): void
    {
        $owner = User::factory()->create();
        $region = $this->regionFor('inmutable');
        $project = $this->projectFor($owner, $region);

        $server = $this->productWithTariff($region, 'Servidor inmutable', 'computacion', 900, 'fijo_mensual', 1500);
        $hours = $this->productWithTariff($region, 'Balance inmutable', 'redes', 25, 'por_hora');
        $storage = $this->productWithTariff($region, 'Repositorio inmutable', 'bases_de_datos', 10, 'por_gb', 200);

        $architecture = $this->saveManualArchitecture($owner, $project, $region, [
            ['product_id' => $server->id, 'quantity' => 2, 'config' => []],
            ['product_id' => $hours->id, 'quantity' => 3, 'config' => ['hours_per_month' => 1.5]],
            ['product_id' => $storage->id, 'quantity' => 1, 'config' => ['gb_per_month' => 2.5]],
        ]);

        $this->emitQuoteFrom($owner, $architecture)->assertRedirect();

        // Estado congelado después de emitir: cabecera y líneas de la BD.
        $frozen = $this->lastQuote();
        $before = $this->captureSnapshot($frozen);

        // Cambios del catálogo más tarde: nombre, tipo, tarifas, modalidad,
        // estado y disponibilidad.
        $server->update(['name' => 'Servidor REEMPLAZADO', 'type' => 'seguridad', 'unit_price' => 99.00]);
        $hours->update(['name' => 'Balance REEMPLAZADA', 'type' => 'backup']);

        ProductAvailability::query()
            ->where('product_id', $server->id)
            ->where('region_id', $region->id)
            ->update(['price_cents' => 9900, 'one_time_price_cents' => 9999, 'pricing_model' => 'por_gb']);
        ProductAvailability::query()
            ->where('product_id', $hours->id)
            ->where('region_id', $region->id)
            ->update(['price_cents' => 1000, 'available' => false]);

        ProductAvailability::query()
            ->where('product_id', $storage->id)
            ->where('region_id', $region->id)
            ->delete();

        $after = $this->captureSnapshot($frozen->refresh()->fresh()->load('lines'));

        $this->assertSame($before, $after, 'El presupuesto histórico no cambia por mutaciones del catálogo.');

        // La modalidad congelada de cada línea tampoco se reescribe.
        $this->assertSame($before['models'], $after['models']);

        // El detalle público sigue mostrando los importes del snapshot pese
        // a los cambios del catálogo: 19.38 €/mes (mensual), 32.00 € (único)
        // y 51.38 € (total inicial).
        $detail = $this->actingAs($owner)->get(route('quotes.show', $frozen))->assertOk();
        $this->assertSeesAmount($detail, 1938);
        $this->assertSeesAmount($detail, 3200);
        $this->assertSeesAmount($detail, 5138);
        $this->assertStringNotContainsString('REEMPLAZADO', (string) $detail->getContent());
    }

    /**
     * Criterio 13 y sección 6: la emisión desde arquitectura NO lee, no
     * modifica ni vacía el carrito; el presupuesto emitido solo contiene los
     * elementos de la arquitectura, no los productos del carrito.
     */
    public function test_emitting_from_architecture_leaves_the_cart_untouched(): void
    {
        $owner = User::factory()->create();
        $region = $this->regionFor('carrito-intacto');
        $project = $this->projectFor($owner, $region);

        $cartProducts = [
            $this->productWithTariff($region, 'Producto de carrito A', 'computacion', 999, 'fijo_mensual', 100),
            $this->productWithTariff($region, 'Producto de carrito B', 'almacenamiento', 888, 'fijo_mensual', 100),
            $this->productWithTariff($region, 'Producto de carrito C', 'redes', 777),
        ];

        foreach ($cartProducts as $index => $product) {
            $item = CartItem::forceCreate([
                'user_id' => $owner->id,
                'product_id' => $product->id,
                'quantity' => $index + 1,
                'config' => ['region_id' => $region->id],
            ]);
            $cartRowsBefore[$item->id] = [$item->product_id, $item->quantity, $item->config];
        }

        $architectureProducts = [
            $this->productWithTariff($region, 'Elemento arquitectura 1', 'bases_de_datos', 500),
            $this->productWithTariff($region, 'Elemento arquitectura 2', 'seguridad', 300),
            $this->productWithTariff($region, 'Elemento arquitectura 4', 'monitorizacion', 200),
        ];

        $architecture = $this->saveManualArchitecture($owner, $project, $region, [
            ['product_id' => $architectureProducts[0]->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $architectureProducts[1]->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $architectureProducts[2]->id, 'quantity' => 1, 'config' => []],
        ]);

        $this->emitQuoteFrom($owner, $architecture)->assertRedirect();

        $quote = $this->lastQuote();

        // El presupuesto surge de la arquitectura, no del carrito.
        $quoteLineNames = $quote->lines->pluck('product_name')->all();
        foreach ($architectureProducts as $product) {
            $this->assertContains($product->name, $quoteLineNames);
        }
        foreach ($cartProducts as $product) {
            $this->assertNotContains($product->name, $quoteLineNames);
        }

        // El carrito del propietario conserva fila a fila sus elementos.
        $this->assertSame(count($cartRowsBefore), CartItem::query()->where('user_id', $owner->id)->count());

        CartItem::query()->where('user_id', $owner->id)->get()->each(function (CartItem $item) use ($cartRowsBefore): void {
            [$productId, $quantity, $config] = $cartRowsBefore[$item->id];
            $this->assertSame($productId, $item->product_id);
            $this->assertSame($quantity, $item->quantity);
            $this->assertSame($config, $item->config);
        });
    }

    /**
     * Criterio 13 y parte de la sección 2: la funcionalidad nueva no cambia
     * el comportamiento del flujo de emisión existente desde carrito: tras
     * haber emitido desde arquitectura, endpoint `quotes.emit` sigue
     * funcionando (crea su presupuesto desde el carrito y deja el carrito
     * vacío, como antes).
     */
    public function test_the_existing_cart_emission_endpoint_still_works_after_an_architecture_emission(): void
    {
        $owner = User::factory()->create();
        $region = $this->regionFor('carrito-flujo');
        $project = $this->projectFor($owner, $region);

        // Carrito con tres productos de dos tipos (criterios del flujo
        // existente de carrito: al menos 3 elementos y 2 tipos).
        $cartTypes = ['computacion', 'almacenamiento', 'computacion'];
        foreach ($cartTypes as $index => $type) {
            $product = $this->productWithTariff($region, 'Carrito flujo '.($index + 1), $type, 1000 + $index);
            CartItem::forceCreate([
                'user_id' => $owner->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'config' => ['region_id' => $region->id],
            ]);
        }

        $architecture = $this->saveManualArchitecture($owner, $project, $region, [
            ['product_id' => $this->productWithTariff($region, 'Arq. flujo 1', 'bases_de_datos', 500)->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $this->productWithTariff($region, 'Arq. flujo 2', 'seguridad', 300)->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $this->productWithTariff($region, 'Arq. flujo 3', 'monitorizacion', 200)->id, 'quantity' => 1, 'config' => []],
        ]);

        $this->emitQuoteFrom($owner, $architecture)->assertRedirect();
        $this->assertSame(1, Quote::query()->count());
        $this->assertSame(3, CartItem::query()->where('user_id', $owner->id)->count());

        // El endpoint PREEXISTENTE del carrito sigue funcionando igual.
        $this->actingAs($owner)
            ->post(route('quotes.emit', $project))
            ->assertRedirect();

        $cartQuote = $this->lastQuote();
        $this->assertSame(3, $cartQuote->lines->count());

        // El flujo de carrito conserva su semántica previa: vacía el
        // carrito tras emitir (sin canibalizar el presupuesto anterior).
        $this->assertSame(0, CartItem::query()->where('user_id', $owner->id)->count());
        $this->assertSame(2, Quote::query()->count());
    }

    /**
     * Criterio 13 y sección 6: la emisión desde arquitectura no produce
     * nada de IA: no crea arquitecturas nuevas ni elementos, no cambia la
     * procedencia manual de la arquitectura y no genera filas ajenas al
     * presupuesto (los artefactos no se pueden inventar sin ficheros ni
     * modelos de IA; la observación estructural es la prueba posible).
     */
    public function test_emission_never_touches_architectures_or_generates_artifacts(): void
    {
        $owner = User::factory()->create();
        $region = $this->regionFor('sin-ia');
        $project = $this->projectFor($owner, $region);

        $server = $this->productWithTariff($region, 'Servidor sin IA', 'computacion', 500);
        $disk = $this->productWithTariff($region, 'Disco sin IA', 'almacenamiento', 300);
        $net = $this->productWithTariff($region, 'Switch sin IA', 'redes', 200);

        $architecture = $this->saveManualArchitecture($owner, $project, $region, [
            ['product_id' => $server->id, 'quantity' => 1, 'config' => ['nucleos' => 4]],
            ['product_id' => $disk->id, 'quantity' => 1, 'config' => []],
            ['product_id' => $net->id, 'quantity' => 1, 'config' => []],
        ]);

        $itemsBefore = ArchitectureItem::query()->where('architecture_id', $architecture->id)->get()
            ->mapWithKeys(fn (ArchitectureItem $item): array => [$item->id => [$item->product_id, $item->quantity, $item->config]])
            ->all();

        $this->emitQuoteFrom($owner, $architecture)->assertRedirect();

        // Ni la arquitectura ni sus elementos cambian ni crecen.
        $architecture->refresh();
        $this->assertSame(Architecture::SOURCE_MANUAL, $architecture->source);
        $this->assertSame($region->id, $architecture->region_id);

        $itemsAfter = ArchitectureItem::query()->where('architecture_id', $architecture->id)->get()
            ->mapWithKeys(fn (ArchitectureItem $item): array => [$item->id => [$item->product_id, $item->quantity, $item->config]])
            ->all();

        $this->assertSame($itemsBefore, $itemsAfter);
        $this->assertDatabaseCount('architectures', 1);
    }

    /**
     * Congela a modo de registro la cabecera y todas las líneas de un
     * presupuesto para comparación completa antes/después (criterio 12).
     *
     * @return array<string, mixed>
     */
    private function captureSnapshot(Quote $quote): array
    {
        return [
            'header' => [
                'project_id' => $quote->project_id,
                'user_id' => $quote->user_id,
                'status' => $quote->status,
                'subtotal_monthly_cents' => $quote->subtotal_monthly_cents,
                'subtotal_one_time_cents' => $quote->subtotal_one_time_cents,
                'total_cents' => $quote->total_cents,
                'issued_at' => $quote->issued_at->format('Y-m-d H:i:s'),
            ],
            // Líneas por id: nombre, tipo, MODALIDAD, cantidad, precio
            // unitario, subtotal y configuración/consumo congelados.
            'lines' => $quote->lines
                ->mapWithKeys(fn (QuoteLine $line): array => [$line->id => [
                    'product_name' => $line->product_name,
                    'product_type' => $line->product_type,
                    'quantity' => $line->quantity,
                    'unit_price_cents' => $line->unit_price_cents,
                    'line_total_cents' => $line->line_total_cents,
                    'config' => $line->config,
                ]])
                ->all(),
            'models' => $quote->lines
                ->mapWithKeys(fn (QuoteLine $line): array => [$line->id => $line->getRawOriginal('pricing_model')])
                ->all(),
        ];
    }
}
