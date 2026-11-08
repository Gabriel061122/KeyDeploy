<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    public function definition(): array
    {
        $suffix = $this->unique()->numberBetween(100000, 999999);

        return [
            'code' => 'reg-'.$suffix,
            'name' => 'Región de pruebas '.$suffix,
        ];
    }
}
