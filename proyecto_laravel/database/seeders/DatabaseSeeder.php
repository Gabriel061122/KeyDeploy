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

        // Usuario demo para desarrollo si no existe
        User::query()->firstOrCreate(
            ['email' => 'demo@keydeploy.test'],
            [
                'name' => 'Cliente Demo KeyDeploy',
                'password' => bcrypt('Password123!'),
                'role' => User::ROLE_CLIENTE,
            ],
        );
    }
}
