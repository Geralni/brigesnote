<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idRegion' => fake()->unique()->numerify('##'),
            'nombreRegion' => strtoupper(fake()->city()),
            'pointX' => fake()->latitude(-18, 0),
            'pointY' => fake()->longitude(-81, -68),
            'zoom' => fake()->numberBetween(6, 12),
        ];
    }
}
