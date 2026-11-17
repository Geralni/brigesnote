<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codMod' => fake()->unique()->numerify('#######'),
            'idDistrito' => fake()->numerify('######'),
            'nombreCenEdu' => strtoupper(fake()->company()),
            'cenPob' => strtoupper(fake()->city()),
            'dirCen' => strtoupper(fake()->streetAddress()),
            'director' => strtoupper(fake()->name()),
            'dis_crecer' => null,
            'dis_juntos' => null,
        ];
    }
}
