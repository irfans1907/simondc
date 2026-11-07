<?php

namespace Database\Factories;

use App\Models\PowerLoadReading;
use App\Models\PowerLoadRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PowerLoadReading>
 */
class PowerLoadReadingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PowerLoadReading>
     */
    protected $model = PowerLoadReading::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'power_load_row_id' => PowerLoadRow::factory(),
            'period' => now()->format('Y-m'),
            'ampere' => fake()->randomFloat(2, 0, 32),
            'user_id' => null,
        ];
    }
}
