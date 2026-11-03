<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'category_id' => Category::factory(),
            'brand' => fake()->company(),
            'type' => strtoupper(fake()->bothify('??-####')),
            'model' => strtoupper(fake()->bothify('MD-####')),
            'serial_number' => strtoupper(fake()->unique()->bothify('SN########??')),
            'capacity' => fake()->randomElement(['1 kVA', '10 kVA', '20 A', '2U', '48U', '10 Gbps', '10 kAh']),
            'manufacture_year' => (string) fake()->numberBetween(2015, 2025),
            'operation_year' => (string) fake()->numberBetween(2016, 2026),
            'quantity' => (string) fake()->numberBetween(1, 10),
            'floor' => fake()->randomElement(['1', '2', '3', 'Ground']),
            'room' => fake()->randomElement(['Ruang Server', 'Ruang UPS', 'Ruang CRAC', 'Data Hall A', 'Data Hall B']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
