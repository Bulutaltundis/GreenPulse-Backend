<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Neighborhood>
 */
class NeighborhoodFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true))
            . ' Mahallesi';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'city' => 'Ankara',
            'district' => fake()->randomElement([
                'Çankaya',
                'Yenimahalle',
                'Keçiören',
                'Etimesgut',
                'Mamak',
            ]),
            'population' => fake()->numberBetween(1000, 50000),
            'is_active' => true,
        ];
    }
}