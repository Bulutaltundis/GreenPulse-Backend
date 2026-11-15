<?php

namespace Database\Factories;

use App\Models\Neighborhood;
use App\Models\Observation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Observation>
 */
class ObservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'neighborhood_id' => Neighborhood::factory(),
            'category' => fake()->randomElement(
                Observation::CATEGORIES
            ),
            'description' => fake()->paragraph(),
            'status' => Observation::STATUS_PENDING,
            'observed_at' => now()->subHours(2),
        ];
    }
}
