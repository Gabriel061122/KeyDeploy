<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        $suffix = substr(bin2hex(random_bytes(5)), 0, 6);

        return [
            'code' => fake()->unique()->lexify('??-????-?'),
            'name' => fake()->city().' Region',
            'country' => 'ES',
            'is_eu' => true,
        ];
    }
}
