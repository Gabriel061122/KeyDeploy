<?php

namespace Tests\Unit\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Region;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T01 - T02: Cálculo de precios según modalidad de tarifa.
 *
 * T01: Producto con modalidad mensual se refleja en el subtotal mensual
 * del presupuesto emitido.
 * T02: Producto con modalidad único se refleja en el subtotal de pago único.
 */
class PriceCalculationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * T01: Un producto con pricing_model='mensual' contribuye al
     * subtotal_monthly_cents del presupuesto, no al one-time.
     */
    public function test_monthly_pricing_product_contributes_to_monthly_subtotal(): void
    {
        $provider = Provider::create(['name' => 'TestProvider']);
        $region = Region::create(['code' => 'test-region', 'name' => 'Región de prueba']);
        $user = User::factory()->create();
        $product = Product::create([
            'code' => 'monthly-server',
            'name' => 'Servidor mensual',
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

        // Crear 3 productos para cumplir reglas (mínimo 3 elementos, 2 tipos)
        $product2 = Product::create([
            'code' => 'monthly-storage',
            'name' => 'Almacenamiento mensual',
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
            'code' => 'monthly-network',
            'name' => 'Red mensual',
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

        CartItem::forceCreate([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'quantity' => 2,
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

        $project = \App\Models\Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => $region->id,
        ]);

        $service = new QuoteService();
        $quote = $service->emitFromCart($user, $project);

        // 2 * 3200 + 1 * 990 + 1 * 1800 = 9190 céntimos mensuales
        $this->assertSame(9190, $quote->subtotal_monthly_cents);
        $this->assertSame(0, $quote->subtotal_one_time_cents);
        $this->assertSame(9190, $quote->total_cents);

        // Verificar que las líneas congeladas tienen el pricing_model correcto
        $line = $quote->lines()->where('product_name', 'Servidor mensual')->firstOrFail();
        $this->assertSame('mensual', $line->pricing_model);
        $this->assertSame(3200, $line->unit_price_cents);
        $this->assertSame(6400, $line->line_total_cents);
    }

    /**
     * T02: Un producto con pricing_model='unico' contribuye al
     * subtotal_one_time_cents del presupuesto, no al mensual.
     */
    public function test_one_time_pricing_product_contributes_to_one_time_subtotal(): void
    {
        $provider = Provider::create(['name' => 'TestProvider']);
        $region = Region::create(['code' => 'test-region-2', 'name' => 'Región de prueba 2']);
        $user = User::factory()->create();

        // Producto con modalidad único
        $backupProduct = Product::create([
            'code' => 'backup-vault',
            'name' => 'Copia de seguridad',
            'type' => 'backup',
            'unit_price' => 12.50,
            'provider_id' => $provider->id,
            'active' => true,
        ]);
        $backupProduct->regions()->attach($region->id, [
            'provider_id' => $provider->id,
            'available' => true,
            'price_cents' => 1250,
            'pricing_model' => 'unico',
        ]);

        // Productos mensuales para cumplir reglas (mínimo 3 elementos, 2 tipos)
        $computeProduct = Product::create([
            'code' => 'compute-monthly',
            'name' => 'Computación mensual',
            'type' => 'computacion',
            'unit_price' => 32.00,
            'provider_id' => $provider->id,
            'active' => true,
        ]);
        $computeProduct->regions()->attach($region->id, [
            'provider_id' => $provider->id,
            'available' => true,
            'price_cents' => 3200,
            'pricing_model' => 'mensual',
        ]);

        $networkProduct = Product::create([
            'code' => 'network-monthly',
            'name' => 'Red mensual',
            'type' => 'red',
            'unit_price' => 18.00,
            'provider_id' => $provider->id,
            'active' => true,
        ]);
        $networkProduct->regions()->attach($region->id, [
            'provider_id' => $provider->id,
            'available' => true,
            'price_cents' => 1800,
            'pricing_model' => 'mensual',
        ]);

        CartItem::forceCreate([
            'user_id' => $user->id,
            'product_id' => $backupProduct->id,
            'quantity' => 3,
            'config' => ['region_id' => $region->id],
        ]);
        CartItem::forceCreate([
            'user_id' => $user->id,
            'product_id' => $computeProduct->id,
            'quantity' => 1,
            'config' => ['region_id' => $region->id],
        ]);
        CartItem::forceCreate([
            'user_id' => $user->id,
            'product_id' => $networkProduct->id,
            'quantity' => 1,
            'config' => ['region_id' => $region->id],
        ]);

        $project = \App\Models\Project::factory()->create([
            'user_id' => $user->id,
            'region_id' => $region->id,
        ]);

        $service = new QuoteService();
        $quote = $service->emitFromCart($user, $project);

        // Mensual: 1 * 3200 + 1 * 1800 = 5000
        // Único: 3 * 1250 = 3750
        // Total: 5000 + 3750 = 8750
        $this->assertSame(5000, $quote->subtotal_monthly_cents);
        $this->assertSame(3750, $quote->subtotal_one_time_cents);
        $this->assertSame(8750, $quote->total_cents);

        // Verificar línea del producto único
        $line = $quote->lines()->where('product_name', 'Copia de seguridad')->firstOrFail();
        $this->assertSame('unico', $line->pricing_model);
        $this->assertSame(1250, $line->unit_price_cents);
        $this->assertSame(3750, $line->line_total_cents);
    }
}
