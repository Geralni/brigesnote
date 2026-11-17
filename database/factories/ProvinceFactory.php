<?php

namespace Database\Factories;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Province>
 */
class ProvinceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idProvincia' => fake()->unique()->numerify('####'),
            'nombreProvincia' => strtoupper(fake()->city()),
            'pointX' => fake()->latitude(-18, 0),
            'pointY' => fake()->longitude(-81, -68),
        ];
    }
}
