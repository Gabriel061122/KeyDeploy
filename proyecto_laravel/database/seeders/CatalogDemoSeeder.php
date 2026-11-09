<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Provider;
use App\Models\Region;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo del catálogo: proveedores, regiones, productos y
 * disponibilidad con precios en céntimos y modelo de tarifa.
 */
class CatalogDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Proveedores
        $nimbus = Provider::firstOrCreate(['name' => 'NimbusCloud']);
        $atlantic = Provider::firstOrCreate(['name' => 'Atlantic Hosting']);

        // Regiones (5 según plantilla)
        $regions = collect([
            ['code' => 'us-east-1', 'name' => 'Virginia, EE. UU.'],
            ['code' => 'us-west-1', 'name' => 'Oregon, EE. UU.'],
            ['code' => 'eu-west-1', 'name' => 'Irlanda'],
            ['code' => 'eu-central-1', 'name' => 'Frankfurt, Alemania'],
            ['code' => 'sa-east-1', 'name' => 'São Paulo, Brasil'],
        ])->mapWithKeys(fn (array $data): array => [$data['code'] => Region::query()->updateOrCreate(
            ['code' => $data['code']],
            ['name' => $data['name']],
        )]);

        // Productos con provider, precio y modelo de tarifa
        // [code, name, type, unit_price, provider, pricing_model, price_cents, active, regiones]
        $products = [
            ['ec2-standard', 'Servidor de computación estándar', 'computacion', 32.00, $nimbus, 'mensual', 3200, true, ['us-east-1', 'eu-west-1', 'sa-east-1']],
            ['ec2-small', 'Servidor de computación reducido', 'computacion', 14.00, $nimbus, 'mensual', 1400, true, ['us-east-1', 'eu-west-1']],
            ['ec2-gpu', 'Servidor GPU para IA', 'computacion_ia', 190.00, $atlantic, 'mensual', 19000, true, ['us-east-1', 'eu-west-1']],
            ['s3-storage', 'Almacenamiento elástico', 'almacenamiento', 9.90, $atlantic, 'por_uso', 990, true, ['us-east-1', 'eu-west-1', 'sa-east-1']],
            ['rds-postgres', 'Base de datos gestionada', 'base_datos', 45.00, $nimbus, 'mensual', 4500, true, ['us-east-1', 'eu-west-1']],
            ['elb-balancer', 'Balanceador de carga', 'red', 18.00, $atlantic, 'mensual', 1800, true, ['us-east-1', 'eu-west-1', 'sa-east-1']],
            ['fw-groups', 'Grupos de seguridad', 'seguridad', 0.00, $nimbus, 'mensual', 0, true, ['us-east-1', 'eu-west-1', 'sa-east-1']],
            ['backup-vault', 'Copias de seguridad', 'backup', 12.50, $atlantic, 'unico', 1250, true, ['us-east-1', 'eu-west-1']],
            ['cloud-watch', 'Monitorización y alertas', 'monitorizacion', 7.25, $nimbus, 'mensual', 725, true, ['us-east-1', 'eu-west-1']],
            ['ia-service', 'Servicio asistente de IA', 'servicio_ia', 25.00, $atlantic, 'por_uso', 2500, true, ['us-east-1', 'eu-west-1']],
            ['support-basic', 'Soporte básico', 'soporte', 30.00, $nimbus, 'por_uso', 3000, true, ['us-east-1', 'eu-west-1', 'sa-east-1']],
            ['ec2-antiguo', 'Servidor retirado', 'computacion', 20.00, $nimbus, 'mensual', 2000, false, ['us-east-1', 'eu-west-1']],
        ];

        foreach ($products as $row) {
            $product = Product::query()->updateOrCreate(['code' => $row[0]], [
                'name' => $row[1],
                'type' => $row[2],
                'unit_price' => $row[3],
                'provider_id' => $row[4]->id,
                'active' => $row[7],
            ]);

            // Sync con pivot data (provider_id, available, price_cents, pricing_model)
            $pivotData = collect($row[8])->mapWithKeys(function (string $code) use ($regions, $row) {
                return [$regions[$code]->id => [
                    'provider_id' => $row[4]->id,
                    'available' => true,
                    'price_cents' => $row[6],
                    'pricing_model' => $row[5],
                ]];
            })->all();
            $product->regions()->sync($pivotData);
        }

        $this->command?->info(sprintf(
            'Catálogo preparado: %d proveedores, %d regiones y %d productos.',
            Provider::count(),
            Region::count(),
            Product::count(),
        ));
    }
}
