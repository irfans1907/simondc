<?php

namespace Database\Factories;

use App\Models\PowerLoadRow;
use App\Models\PowerPdu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PowerLoadRow>
 */
class PowerLoadRowFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PowerLoadRow>
     */
    protected $model = PowerLoadRow::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'power_pdu_id' => PowerPdu::factory(),
            'customer' => fake()->company(),
            'rack' => fake()->bothify('NLBL2####'),
            'rack_type' => 'FULL',
            'mcb' => (string) fake()->numberBetween(1, 40),
            'capacity_amp' => null,
            'phase' => fake()->randomElement(['R', 'S', 'T']),
            'service' => null,
            'sort_order' => 0,
        ];
    }
}
