<?php

namespace Database\Factories;

use App\Models\District;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<District>
 */
class DistrictFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idDistrito' => fake()->unique()->numerify('######'),
            'nombreDistrito' => strtoupper(fake()->city()),
        ];
    }
}
