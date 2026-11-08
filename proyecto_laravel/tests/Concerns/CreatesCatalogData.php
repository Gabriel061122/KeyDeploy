<?php

namespace Tests\Concerns;

use App\Models\Product;
use App\Models\Region;
use App\Models\User;

/**
 * Datos de catálogo reutilizables para las pruebas: una región base,
 * productos activos/inactivos y disponibilidad por región tal como
 * declara el contrato de integración (products + product_region).
 */
trait CreatesCatalogData
{
    /**
     * Región con código siempre distinto: los mismos datos de catálogo
     * se comparten entre pruebas y algunas crean varias regiones.
     */
    protected function createRegion(string $code = 'reg'): Region
    {
        return Region::query()->create([
            'code' => $this->uniqueCode($code),
            'name' => 'Región de pruebas '.$code,
        ]);
    }

    /**
     * Crea un producto y su disponibilidad en las regiones indicadas.
     *
     * @param  list<string>  $regionIds
     */
    protected function createProduct(string $code, string $type, array $regionIds, bool $active = true): Product
    {
        $product = Product::query()->create([
            'code' => $code,
            'name' => 'Producto '.$code,
            'type' => $type,
            'unit_price' => 10.00,
            'active' => $active,
        ]);

        $product->regions()->sync($regionIds);

        return $product;
    }

    /**
     * Tres elementos válidos: computación + almacenamiento + red
     * disponibles en la región dada (cumuple: 3 elementos, 2+ tipos).
     */
    protected function validItemsFor(Region $region): array
    {
        $compute = $this->createProduct($this->uniqueCode('comp'), 'computacion', [$region->id]);
        $storage = $this->createProduct($this->uniqueCode('store'), 'almacenamiento', [$region->id]);
        $network = $this->createProduct($this->uniqueCode('net'), 'red', [$region->id]);

        return [
            ['product_id' => $compute->id, 'quantity' => 2, 'config' => '{"instancia": "t3.medium"}'],
            ['product_id' => $storage->id, 'quantity' => 1, 'config' => '{"disco_gb": 100}'],
            ['product_id' => $network->id, 'quantity' => 1, 'config' => '{}'],
        ];
    }

    protected function uniqueCode(string $prefix): string
    {
        return $prefix.'-'.substr(bin2hex(random_bytes(5)), 0, 6);
    }

    protected function anotherCliente(): User
    {
        return User::factory()->create();
    }
}
