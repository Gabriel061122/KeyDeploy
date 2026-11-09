<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seeder oficial del catálogo con los mínimos de la memoria UT2 (2 proveedores, 5 regiones, 15 productos, 6 tipos)
        $this->call(CatalogSeeder::class);

        if (app()->environment('local', 'testing', 'development')) {
            // Usuario de demostración
            User::firstOrCreate(
                ['email' => 'demo@keydeploy.test'],
                [
                    'name' => 'Cliente de demostración',
                    'password' => 'clave1234',
                    'role' => User::ROLE_CLIENTE,
                ],
            );

            // Usuario de desarrollo
            User::firstOrCreate(
                ['email' => 'dev@keydeploy.test'],
                [
                    'name' => 'Usuario de desarrollo',
                    'password' => 'password',
                    'role' => User::ROLE_CLIENTE,
                ],
            );
        }
    }
}
