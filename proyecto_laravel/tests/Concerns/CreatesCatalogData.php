<?php

namespace Tests\Concerns;

use App\Enums\PricingModel;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductAvailability;
use App\Models\Provider;
use App\Models\Region;
use App\Models\User;

/**
 * Datos de catálogo reutilizables para las pruebas según el modelo oficial UT2:
 * providers, regions, products y product_availabilities.
 */
trait CreatesCatalogData
{
    protected ?Provider $defaultProvider = null;

    protected function getProvider(): Provider
    {
        if ($this->defaultProvider === null) {
            $this->defaultProvider = Provider::query()->firstOrCreate(['name' => 'NimbusCloud Test']);
        }

        return $this->defaultProvider;
    }

    /**
     * Región con código siempre distinto: los mismos datos de catálogo
     * se comparten entre pruebas y algunas crean varias regiones.
     */
    protected function createRegion(string $code = 'reg'): Region
    {
        return Region::query()->create([
            'code' => $this->uniqueCode($code),
            'name' => 'Región de pruebas '.$code,
            'country' => 'ES',
            'is_eu' => true,
        ]);
    }

    /**
     * Crea un producto y su disponibilidad en las regiones indicadas.
     *
     * @param  list<int>  $regionIds
     */
    protected function createProduct(string $name, string|ProductType $type, array $regionIds, bool $active = true): Product
    {
        $typeVal = $type instanceof ProductType ? $type->value : $type;

        $product = Product::query()->create([
            'provider_id' => $this->getProvider()->id,
            'name' => 'Producto '.$name,
            'description' => 'Descripción de pruebas para '.$name,
            'type' => $typeVal,
            'billing_unit' => 'mes',
            'config' => ['vcpu' => 2, 'ram_gb' => 4],
            'active' => $active,
        ]);

        foreach ($regionIds as $regionId) {
            ProductAvailability::query()->create([
                'product_id' => $product->id,
                'region_id' => $regionId,
                'available' => true,
                'price_cents' => 3000,
                'pricing_model' => PricingModel::FijoMensual,
            ]);
        }

        return $product;
    }

    /**
     * Tres elementos válidos: computación + almacenamiento + redes
     * disponibles en la región dada (cumple: al menos 3 elementos, 2+ tipos).
     */
    protected function validItemsFor(Region $region): array
    {
        $compute = $this->createProduct($this->uniqueCode('comp'), ProductType::Computacion, [$region->id]);
        $storage = $this->createProduct($this->uniqueCode('store'), ProductType::Almacenamiento, [$region->id]);
        $network = $this->createProduct($this->uniqueCode('net'), ProductType::Redes, [$region->id]);

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
