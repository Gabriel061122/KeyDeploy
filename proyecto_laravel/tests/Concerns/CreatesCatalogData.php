<?php

namespace Tests\Concerns;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Datos de catálogo reutilizables para las pruebas: una región base,
 * productos activos/inactivos y disponibilidad por región tal como
 * declara el contrato de integración (products + product_availabilities).
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
    protected function createProduct(string $name, string|ProductType $type, array $regionIds, bool $active = true, array $attributes = []): Product
    {
        $provider = Provider::first() ?? Provider::create(['name' => 'TestProvider']);
        $code = $this->uniqueCode(Str::slug($name));

        $product = Product::query()->create([
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'unit_price' => 10.00,
            'provider_id' => $provider->id,
            'active' => $active,
        ] + ($attributes !== [] ? ['config' => $attributes] : []));

        $pivotData = collect($regionIds)->mapWithKeys(function ($regionId) use ($provider) {
            return [$regionId => [
                'provider_id' => $provider->id,
                'available' => true,
                'price_cents' => 1000,
                'pricing_model' => 'fijo_mensual',
            ]];
        })->all();

        $product->regions()->sync($pivotData);

        return $product;
    }

    /**
     * Tres elementos válidos: computación + almacenamiento + red
     * disponibles en la región dada (cumple: 3 elementos, 2+ tipos).
     *
     * El contrato actual no acepta configuración JSON del navegador:
     * los elementos solo declaran producto, cantidad y consumo mensual
     * opcional (`items[*][usage]`, requerido solo para tarifas por hora
     * o por GB). Todos los productos de este ayudante son fijo_mensual,
     * así que el consumo no es necesario.
     */
    protected function validItemsFor(Region $region): array
    {
        $compute = $this->createProduct($this->uniqueCode('comp'), ProductType::Computacion, [$region->id]);
        $storage = $this->createProduct($this->uniqueCode('store'), ProductType::Almacenamiento, [$region->id]);
        $network = $this->createProduct($this->uniqueCode('net'), ProductType::Redes, [$region->id]);

        return [
            ['product_id' => $compute->id, 'quantity' => 2],
            ['product_id' => $storage->id, 'quantity' => 1],
            ['product_id' => $network->id, 'quantity' => 1],
        ];
    }

    /**
     * Huecos vacíos del formulario (sin producto ni cantidad): el
     * servidor los descarta sin errores.
     *
     * @return list<array{product_id: '', quantity: '', usage: ''}>
     */
    protected function emptySlots(int $count): array
    {
        return array_fill(0, $count, ['product_id' => '', 'quantity' => '', 'usage' => '']);
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
