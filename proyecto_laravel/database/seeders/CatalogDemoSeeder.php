<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Region;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Datos de ejemplo del catálogo para poder desarrollar y probar los
 * módulos de proyectos, requerimientos y arquitecturas manuales (EP04)
 * mientras el CRUD del catálogo (RF12/EP03) queda implementado por el
 * miembro responsable. Es un seed de CONTRATO DE INTEGRACIÓN, no la
 * gestión del catálogo: solo crea regiones, productos y disponibilidad.
 */
class CatalogDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $regions = collect([
            ['code' => 'us-east-1', 'name' => 'Virginia, EE. UU.'],
            ['code' => 'eu-west-1', 'name' => 'Irlanda'],
            ['code' => 'sa-east-1', 'name' => 'São Paulo, Brasil'],
        ])->mapWithKeys(fn (array $data): array => [$data['code'] => Region::query()->updateOrCreate(
            ['code' => $data['code']],
            ['name' => $data['name']],
        )]);

        collect([
            // [code, name, type, precio mensual, active, regiones disponibles]
            ['ec2-standard', 'Servidor de computación estándar', 'computacion', 32.00, true, ['us-east-1', 'eu-west-1', 'sa-east-1']],
            ['ec2-small', 'Servidor de computación reducido', 'computacion', 14.00, true, ['us-east-1', 'eu-west-1']],
            ['s3-storage', 'Almacenamiento elástico', 'almacenamiento', 9.90, true, ['us-east-1', 'eu-west-1', 'sa-east-1']],
            ['rds-postgres', 'Base de datos gestionada', 'base_datos', 45.00, true, ['us-east-1', 'eu-west-1']],
            ['elb-balancer', 'Balanceador de carga', 'red', 18.00, true, ['us-east-1', 'eu-west-1', 'sa-east-1']],
            ['fw-groups', 'Grupos de seguridad y cortafuego', 'seguridad', 0.00, true, ['us-east-1', 'eu-west-1', 'sa-east-1']],
            ['backup-vault', 'Copias de seguridad', 'backup', 12.50, true, ['us-east-1', 'eu-west-1']],
            ['cloud-watch', 'Monitorización y alertas', 'monitorizacion', 7.25, true, ['us-east-1']],
            ['ia-training', 'Entrenamiento con GPU', 'computacion_ia', 190.00, true, ['us-east-1', 'eu-west-1']],
            ['ia-service', 'Servicio asistente de IA', 'servicio_ia', 25.00, true, ['us-east-1', 'eu-west-1']],
            ['support-basic', 'Plano de soporte básico', 'soporte', 30.00, true, ['us-east-1', 'eu-west-1', 'sa-east-1']],
            ['ec2-antiguo', 'Servidor retirado', 'computacion', 20.00, false, ['us-east-1', 'eu-west-1', 'sa-east-1']],
        ])->each(function (array $row) use ($regions): void {
            $product = Product::query()->updateOrCreate(['code' => $row[0]], [
                'name' => $row[1],
                'type' => $row[2],
                'unit_price' => $row[3],
                'active' => $row[4],
            ]);

            $product->regions()->sync(collect($row[5])->map(
                fn (string $code): int => $regions[$code]->id,
            )->all());
        });

        $this->command?->info(sprintf(
            'Catálogo de demostración preparado: %d regiones y %d productos.',
            Region::count(),
            Product::count(),
        ));
    }
}
