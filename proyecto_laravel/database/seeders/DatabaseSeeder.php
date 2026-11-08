<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed de la aplicación: catálogo de demostración (contrato de
     * integración con EP03) y, en entornos de desarrollo, una cuenta de
     * CLIENTE de ejemplo para probar la interfaz.
     */
    public function run(): void
    {
        $this->call(CatalogDemoSeeder::class);

        if (app()->environment('local', 'testing', 'development')) {
            User::firstOrCreate(
                ['email' => 'demo@keydeploy.test'],
                [
                    'name' => 'Cliente de demostración',
                    'password' => 'clave1234',
                    'role' => User::ROLE_CLIENTE,
                ],
            );
        }
    }
}
