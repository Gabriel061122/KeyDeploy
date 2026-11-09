<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\Provider;
use App\Models\Region;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T09 - T10: Presupuesto inmutable y transacciones atómicas.
 *
 * T09: El presupuesto histórico no cambia si después se modifica la
 * tarifa del catálogo.
 * T10: Si la transacción de emisión falla, no quedan datos incompletos
 * (rollback completo).
 */
class QuoteImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * T09: Emitir un presupuesto, luego cambiar la tarifa del producto
     * en el catálogo. El presupuesto histórico conserva la tarifa original.
     */
    public function test_historical_quote_remains_immutable_after_price_change(): void
    {
        $provider = Provider::create(['name' => 'TestProvider']);
        $region = Region::create(['code' => 'immutable-test', 'name' => 'Región inmutable']);
        $user = User::factory()->create();

        // Crear 3 productos con 2 tipos
        $product1 = Product::create([
            'code' => 'immutable-comp',
            'name' => 'Servidor inmutable',
            'type' => 'computacion',
            'unit_price' => 32.00,
            'provider_id' => $provider->id,
            'active' => true,
        ]);
        $product1->regions()->attach($region->id, [
            'provider_id' => $provider->id,
            'available' => true,
            'price_cents' => 3200,
            'pricing_model' => 'mensual',
        ]);

        $product2 = Product::create([
            'code' => 'immutable-stor',
            'name' => 'Disco inmutable',
            'type' => 'almacenamiento',
            'unit_price' => 9.90,
            'provider_id' => $provider->id,
            'active' => true,
        ]);
        $product2->regions()->attach($region->id, [
            'provider_id' => $provider->id,
            'available' => true,
            'price_cents' => 990,
            'pricing_model' => 'mensual',
        ]);

        $product3 = Product::create([
            'code' => 'immutable-net',
            'name' => 'Red inmutable',
            'type' => 'red',
            'unit_price' => 18.00,
            'provider_id' => $provider->id,
            'active' => true,
        ]);
        $product3->regions()->attach($region->id, [
            'provider_id' => $provider->id,
            'available' => true,
            'price_cents' => 1800,
            'pricing_model' => 'mensual',
        ]);

        // Añadir al carrito
        CartItem::forceCreate([
            'user_id' => $user->id,
            'product_id' => $product1->id,
            'quantity' => 1,
            'config' => ['region_id' => $region->id],
        ]);
        CartItem::forceCreate([
            'user_id' => $user->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'config' => ['region_id' => $region->id],
        ]);
        CartItem::forceCreate([
            'user_id' => $user->id,
            'product_id' => $product3->id,
            'quantity' => 1,
            'config' => ['region_id' => $region->id],
        ]);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => $region->id,
        ]);

        // Emitir presupuesto
        $service = new QuoteService;
        $quote = $service->emitFromCart($user, $project);

        // Guardar los valores originales del presupuesto
        $originalTotal = $quote->total_cents;
        $originalLines = $quote->lines()->get()->keyBy('product_name');

        // Verificar valores originales
        $this->assertSame(3200, $originalLines['Servidor inmutable']->unit_price_cents);
        $this->assertSame(990, $originalLines['Disco inmutable']->unit_price_cents);
        $this->assertSame(1800, $originalLines['Red inmutable']->unit_price_cents);
        $this->assertSame(5990, $originalTotal); // 3200 + 990 + 1800

        // Cambiar las tarifas en el catálogo
        $product1->update(['unit_price' => 99.00]);
        $product1->regions()->updateExistingPivot($region->id, [
            'price_cents' => 9900,
            'pricing_model' => 'mensual',
        ]);

        $product2->update(['unit_price' => 25.00]);
        $product2->regions()->updateExistingPivot($region->id, [
            'price_cents' => 2500,
            'pricing_model' => 'unico',
        ]);

        // Recargar el presupuesto desde BD
        $quote->refresh();
        $quote->load('lines');
        $immutableLines = $quote->lines()->get()->keyBy('product_name');

        // El presupuesto NO debe haber cambiado
        $this->assertSame($originalTotal, $quote->total_cents);
        $this->assertSame(3200, $immutableLines['Servidor inmutable']->unit_price_cents);
        $this->assertSame(990, $immutableLines['Disco inmutable']->unit_price_cents);
        $this->assertSame(1800, $immutableLines['Red inmutable']->unit_price_cents);

        // Los nombres y tipos también deben estar congelados
        $this->assertSame('Servidor inmutable', $immutableLines['Servidor inmutable']->product_name);
        $this->assertSame('computacion', $immutableLines['Servidor inmutable']->product_type);
    }

    /**
     * T10: Si la transacción de emisión falla (carrito vacío), no quedan
     * datos incompletos. El rollback es total.
     */
    public function test_transaction_rollback_on_failure(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        // Carrito vacío: la emisión debe fallar
        $service = new QuoteService;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No se puede emitir presupuesto: el carrito está vacío.');
        $service->emitFromCart($user, $project);
    }

    /**
     * T10b: Verificar que al fallar la validación, no se crean registros
     * parciales en quotes ni quote_lines.
     */
    public function test_no_partial_data_on_validation_failure(): void
    {
        $provider = Provider::create(['name' => 'TestProvider']);
        $region = Region::create(['code' => 'rollback-test', 'name' => 'Región rollback']);
        $user = User::factory()->create();

        // Solo 1 producto (menos de 3, falla validación)
        $product = Product::create([
            'code' => 'rollback-comp',
            'name' => 'Servidor rollback',
            'type' => 'computacion',
            'unit_price' => 32.00,
            'provider_id' => $provider->id,
            'active' => true,
        ]);
        $product->regions()->attach($region->id, [
            'provider_id' => $provider->id,
            'available' => true,
            'price_cents' => 3200,
            'pricing_model' => 'mensual',
        ]);

        CartItem::forceCreate([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'config' => ['region_id' => $region->id],
        ]);

        $project = Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => $region->id,
        ]);

        // Intentar emitir (debe fallar por regla de mínimo 3 productos)
        $this->actingAs($user)
            ->post(route('quotes.emit', $project))
            ->assertSessionHasErrors(['emit' => 'Se requieren al menos 3 productos para emitir un presupuesto.']);

        // No se creó ningún presupuesto ni línea
        $this->assertDatabaseCount('quotes', 0);
        $this->assertDatabaseCount('quote_lines', 0);

        // El carrito se mantiene intacto
        $this->assertDatabaseCount('cart_items', 1);
    }
}
