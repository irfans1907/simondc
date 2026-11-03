<?php

namespace Database\Factories;

use App\Models\RackType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RackType>
 */
class RackTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'U Rack',
                'Sub Rack',
                'Full Rack Server',
                'Full Rack Network',
            ]),
            'capacity' => fake()->randomElement(['1U', '15U', '42U', '45U']),
        ];
    }
}
