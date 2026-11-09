<?php

namespace Database\Seeders;

use App\Enums\PricingModel;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductAvailability;
use App\Models\Provider;
use App\Models\Region;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder oficial del catálogo según la sección 6.1 de la plantilla UT2.
 *
 * Mínimos verificables exigidos por la memoria:
 * - 2 proveedores: NimbusCloud, Atlantic Hosting.
 * - 5 regiones: eu-west, eu-central, us-east, us-west, ap-south.
 * - 15 productos activos distribuidos en los 6 tipos funcionales.
 * - 6 tipos funcionales: computacion, almacenamiento, bases_de_datos, redes, seguridad, backup.
 * - Casos específicos para pruebas de disponibilidad por región (ADR-001):
 *   * VM Básica disponible en eu-west, eu-central y us-east.
 *   * VM Premium sin disponibilidad en us-east.
 *   * 1 producto retirado (active=false) para verificar bloqueo.
 */
class CatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 1. Proveedores (2 exigidos)
        $nimbus = Provider::query()->updateOrCreate(['name' => 'NimbusCloud']);
        $atlantic = Provider::query()->updateOrCreate(['name' => 'Atlantic Hosting']);

        // 2. Regiones (5 exigidas)
        $regions = collect([
            'eu-west' => Region::query()->updateOrCreate(['code' => 'eu-west'], [
                'name' => 'Europa Occidental (Irlanda)',
                'country' => 'IE',
                'is_eu' => true,
            ]),
            'eu-central' => Region::query()->updateOrCreate(['code' => 'eu-central'], [
                'name' => 'Europa Central (Fráncfort)',
                'country' => 'DE',
                'is_eu' => true,
            ]),
            'us-east' => Region::query()->updateOrCreate(['code' => 'us-east'], [
                'name' => 'Norteamérica Este (N. Virginia)',
                'country' => 'US',
                'is_eu' => false,
            ]),
            'us-west' => Region::query()->updateOrCreate(['code' => 'us-west'], [
                'name' => 'Norteamérica Oeste (Oregón)',
                'country' => 'US',
                'is_eu' => false,
            ]),
            'ap-south' => Region::query()->updateOrCreate(['code' => 'ap-south'], [
                'name' => 'Asia-Pacífico (Bombay)',
                'country' => 'IN',
                'is_eu' => false,
            ]),
        ]);

        // 3. Productos (15 activos + 1 retirado en los 6 tipos)
        $productsData = [
            // Computación (3)
            [
                'provider' => $nimbus,
                'name' => 'VM Básica General',
                'description' => 'Instancia de computación de propósito general.',
                'type' => ProductType::Computacion,
                'billing_unit' => 'mes',
                'config' => ['vcpu' => 2, 'ram_gb' => 4],
                'active' => true,
                // Caso doc: disponible en eu-west, eu-central y us-east
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 3200, 'pricing_model' => PricingModel::FijoMensual],
                    'eu-central' => ['available' => true, 'price_cents' => 3400, 'pricing_model' => PricingModel::FijoMensual],
                    'us-east' => ['available' => true, 'price_cents' => 2900, 'pricing_model' => PricingModel::FijoMensual],
                ],
            ],
            [
                'provider' => $nimbus,
                'name' => 'VM Premium Rendimiento',
                'description' => 'Instancia de computación optimizada para CPU intensiva.',
                'type' => ProductType::Computacion,
                'billing_unit' => 'mes',
                'config' => ['vcpu' => 8, 'ram_gb' => 32],
                'active' => true,
                // Caso doc: sin disponibilidad en us-east
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 12500, 'pricing_model' => PricingModel::FijoMensual],
                    'eu-central' => ['available' => true, 'price_cents' => 13000, 'pricing_model' => PricingModel::FijoMensual],
                    'us-west' => ['available' => true, 'price_cents' => 12000, 'pricing_model' => PricingModel::FijoMensual],
                    'ap-south' => ['available' => true, 'price_cents' => 13500, 'pricing_model' => PricingModel::FijoMensual],
                ],
            ],
            [
                'provider' => $atlantic,
                'name' => 'Servidor de Cálculo IA GPU',
                'description' => 'Acelerador de cálculo para entrenamiento con GPU dedicada.',
                'type' => ProductType::Computacion,
                'billing_unit' => 'hora',
                'config' => ['vcpu' => 16, 'ram_gb' => 64],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 180, 'pricing_model' => PricingModel::PorHora],
                    'eu-central' => ['available' => true, 'price_cents' => 195, 'pricing_model' => PricingModel::PorHora],
                    'us-east' => ['available' => true, 'price_cents' => 165, 'pricing_model' => PricingModel::PorHora],
                ],
            ],

            // Almacenamiento (3)
            [
                'provider' => $atlantic,
                'name' => 'Almacenamiento Bloque SSD NVMe',
                'description' => 'Volumen de disco de alto rendimiento en IOPS.',
                'type' => ProductType::Almacenamiento,
                'billing_unit' => 'gb_mes',
                'config' => ['capacity_gb' => 100, 'storage_type' => 'nvme'],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 12, 'pricing_model' => PricingModel::PorGb],
                    'eu-central' => ['available' => true, 'price_cents' => 13, 'pricing_model' => PricingModel::PorGb],
                    'us-east' => ['available' => true, 'price_cents' => 10, 'pricing_model' => PricingModel::PorGb],
                    'us-west' => ['available' => true, 'price_cents' => 10, 'pricing_model' => PricingModel::PorGb],
                    'ap-south' => ['available' => true, 'price_cents' => 14, 'pricing_model' => PricingModel::PorGb],
                ],
            ],
            [
                'provider' => $nimbus,
                'name' => 'Almacenamiento Objetos S3',
                'description' => 'Almacenamiento de objetos elástico y de alta durabilidad.',
                'type' => ProductType::Almacenamiento,
                'billing_unit' => 'gb_mes',
                'config' => ['capacity_gb' => 500, 'storage_type' => 'object_storage'],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 3, 'pricing_model' => PricingModel::PorGb],
                    'eu-central' => ['available' => true, 'price_cents' => 4, 'pricing_model' => PricingModel::PorGb],
                    'us-east' => ['available' => true, 'price_cents' => 2, 'pricing_model' => PricingModel::PorGb],
                ],
            ],
            [
                'provider' => $atlantic,
                'name' => 'Almacenamiento Archivo Frío',
                'description' => 'Almacenamiento de archivo a largo plazo para copias históricas.',
                'type' => ProductType::Almacenamiento,
                'billing_unit' => 'gb_mes',
                'config' => ['capacity_gb' => 1000, 'storage_type' => 'cold_archive'],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 1, 'pricing_model' => PricingModel::PorGb],
                    'us-east' => ['available' => true, 'price_cents' => 1, 'pricing_model' => PricingModel::PorGb],
                ],
            ],

            // Bases de datos (2)
            [
                'provider' => $nimbus,
                'name' => 'PostgreSQL Gestionado HA',
                'description' => 'Motor relacional PostgreSQL con replicación multi-AZ.',
                'type' => ProductType::BasesDeDatos,
                'billing_unit' => 'mes',
                'config' => ['engine' => 'postgresql', 'storage_gb' => 50],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 8500, 'pricing_model' => PricingModel::FijoMensual],
                    'eu-central' => ['available' => true, 'price_cents' => 8900, 'pricing_model' => PricingModel::FijoMensual],
                    'us-east' => ['available' => true, 'price_cents' => 7900, 'pricing_model' => PricingModel::FijoMensual],
                ],
            ],
            [
                'provider' => $atlantic,
                'name' => 'MySQL Gestionado Standalone',
                'description' => 'Instancia única de MySQL optimizada para aplicaciones web.',
                'type' => ProductType::BasesDeDatos,
                'billing_unit' => 'mes',
                'config' => ['engine' => 'mysql', 'storage_gb' => 25],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 4500, 'pricing_model' => PricingModel::FijoMensual],
                    'eu-central' => ['available' => true, 'price_cents' => 4700, 'pricing_model' => PricingModel::FijoMensual],
                    'us-east' => ['available' => true, 'price_cents' => 4200, 'pricing_model' => PricingModel::FijoMensual],
                    'us-west' => ['available' => true, 'price_cents' => 4200, 'pricing_model' => PricingModel::FijoMensual],
                ],
            ],

            // Redes (3)
            [
                'provider' => $nimbus,
                'name' => 'Balanceador de Carga Elástico',
                'description' => 'Distribución de tráfico HTTP/HTTPS entre múltiples nodos.',
                'type' => ProductType::Redes,
                'billing_unit' => 'mes',
                'config' => ['bandwidth_mbps' => 1000],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 2500, 'pricing_model' => PricingModel::FijoMensual],
                    'eu-central' => ['available' => true, 'price_cents' => 2600, 'pricing_model' => PricingModel::FijoMensual],
                    'us-east' => ['available' => true, 'price_cents' => 2200, 'pricing_model' => PricingModel::FijoMensual],
                    'us-west' => ['available' => true, 'price_cents' => 2200, 'pricing_model' => PricingModel::FijoMensual],
                    'ap-south' => ['available' => true, 'price_cents' => 2700, 'pricing_model' => PricingModel::FijoMensual],
                ],
            ],
            [
                'provider' => $atlantic,
                'name' => 'IP Pública Elástica Estática',
                'description' => 'Dirección IPv4 pública fija con protección contra caídas.',
                'type' => ProductType::Redes,
                'billing_unit' => 'mes',
                'config' => ['bandwidth_mbps' => 100],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 400, 'pricing_model' => PricingModel::FijoMensual],
                    'eu-central' => ['available' => true, 'price_cents' => 400, 'pricing_model' => PricingModel::FijoMensual],
                    'us-east' => ['available' => true, 'price_cents' => 350, 'pricing_model' => PricingModel::FijoMensual],
                ],
            ],
            [
                'provider' => $nimbus,
                'name' => 'Puerta de Enlace VPN Corporativa',
                'description' => 'Conexión tunelada cifrada IPSec hacia la sede del cliente.',
                'type' => ProductType::Redes,
                'billing_unit' => 'mes',
                'config' => ['bandwidth_mbps' => 500],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 4900, 'pricing_model' => PricingModel::FijoMensual],
                    'us-east' => ['available' => true, 'price_cents' => 4500, 'pricing_model' => PricingModel::FijoMensual],
                ],
            ],

            // Seguridad (2)
            [
                'provider' => $nimbus,
                'name' => 'Cortafuegos Web WAF Gestionado',
                'description' => 'Protección en capa 7 contra ataques OWASP Top 10 y DDoS.',
                'type' => ProductType::Seguridad,
                'billing_unit' => 'mes',
                'config' => ['rules_count' => 50],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 3000, 'pricing_model' => PricingModel::FijoMensual],
                    'eu-central' => ['available' => true, 'price_cents' => 3200, 'pricing_model' => PricingModel::FijoMensual],
                    'us-east' => ['available' => true, 'price_cents' => 2800, 'pricing_model' => PricingModel::FijoMensual],
                ],
            ],
            [
                'provider' => $atlantic,
                'name' => 'Gestor de Certificados SSL/TLS',
                'description' => 'Emisión, renovación y aprovisionamiento automático de certificados.',
                'type' => ProductType::Seguridad,
                'billing_unit' => 'mes',
                'config' => ['rules_count' => 10],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 0, 'pricing_model' => PricingModel::FijoMensual],
                    'eu-central' => ['available' => true, 'price_cents' => 0, 'pricing_model' => PricingModel::FijoMensual],
                    'us-east' => ['available' => true, 'price_cents' => 0, 'pricing_model' => PricingModel::FijoMensual],
                ],
            ],

            // Backup (2)
            [
                'provider' => $atlantic,
                'name' => 'Copias de Seguridad Automatizadas',
                'description' => 'Capturas de estado diarias retenidas en almacenamiento secundario.',
                'type' => ProductType::Backup,
                'billing_unit' => 'mes',
                'config' => ['retention_days' => 30],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 1500, 'pricing_model' => PricingModel::FijoMensual],
                    'eu-central' => ['available' => true, 'price_cents' => 1600, 'pricing_model' => PricingModel::FijoMensual],
                    'us-east' => ['available' => true, 'price_cents' => 1400, 'pricing_model' => PricingModel::FijoMensual],
                ],
            ],
            [
                'provider' => $nimbus,
                'name' => 'Bóveda de Respaldo Inmutable',
                'description' => 'Retención inmutable con protección contra ransomware (WORM).',
                'type' => ProductType::Backup,
                'billing_unit' => 'mes',
                'config' => ['retention_days' => 365],
                'active' => true,
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 3500, 'pricing_model' => PricingModel::FijoMensual],
                    'us-east' => ['available' => true, 'price_cents' => 3200, 'pricing_model' => PricingModel::FijoMensual],
                ],
            ],

            // Producto retirado / inactivo (1)
            [
                'provider' => $nimbus,
                'name' => 'Servidor Heredado Legacy',
                'description' => 'Servidor de generación previa dado de baja.',
                'type' => ProductType::Computacion,
                'billing_unit' => 'mes',
                'config' => ['vcpu' => 1, 'ram_gb' => 1],
                'active' => false, // Retirado
                'availabilities' => [
                    'eu-west' => ['available' => true, 'price_cents' => 1000, 'pricing_model' => PricingModel::FijoMensual],
                ],
            ],
        ];

        foreach ($productsData as $data) {
            $product = Product::query()->updateOrCreate(
                [
                    'provider_id' => $data['provider']->id,
                    'name' => $data['name'],
                ],
                [
                    'description' => $data['description'],
                    'type' => $data['type'],
                    'billing_unit' => $data['billing_unit'],
                    'config' => $data['config'],
                    'active' => $data['active'],
                ],
            );

            foreach ($data['availabilities'] as $regionCode => $avail) {
                if ($regions->has($regionCode)) {
                    ProductAvailability::query()->updateOrCreate(
                        [
                            'product_id' => $product->id,
                            'region_id' => $regions[$regionCode]->id,
                        ],
                        [
                            'available' => $avail['available'],
                            'price_cents' => $avail['price_cents'],
                            'pricing_model' => $avail['pricing_model'],
                        ],
                    );
                }
            }
        }

        $this->command?->info(sprintf(
            'Catálogo oficial completado: %d proveedores, %d regiones, %d productos activos.',
            Provider::query()->count(),
            Region::query()->count(),
            Product::query()->where('active', true)->count(),
        ));
    }
}
