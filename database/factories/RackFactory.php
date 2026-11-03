<?php

namespace Database\Factories;

use App\Models\Rack;
use App\Models\RackType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rack>
 */
class RackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = RackType::query()->inRandomOrder()->first()
            ?? RackType::factory()->create();

        return [
            'rack_code' => 'RACK-'.fake()->unique()->numberBetween(1, 99999),
            'floor' => (string) fake()->numberBetween(1, 5),
            'room' => fake()->randomElement(['NOC', 'Data Hall A', 'Data Hall B', 'Ruang UPS']),
            'status' => Rack::DEFAULT_STATUS,
            'brand' => fake()->randomElement(['APC', 'Rittal', 'Vertiv', 'Chicony', 'Star']),
            'type' => $type->name,
            'capacity' => $type->capacity,
            'dimension' => fake()->randomElement(['600x1000x2000mm', '600x800x2200mm', '800x1000x2000mm']),
            'pdu_outlet_1' => fake()->randomElement(Rack::PDU_STATES),
            'pdu_outlet_2' => fake()->randomElement(Rack::PDU_STATES),
            'manufacture_year' => (string) fake()->numberBetween(2015, 2025),
            'operation_year' => (string) fake()->numberBetween(2016, 2026),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
