<?php

namespace Database\Factories;

use App\Models\PowerPanel;
use App\Models\PowerPanelReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PowerPanelReading>
 */
class PowerPanelReadingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PowerPanelReading>
     */
    protected $model = PowerPanelReading::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'power_panel_id' => PowerPanel::factory(),
            'period' => now()->format('Y-m'),
            'amp_r' => fake()->randomFloat(2, 0, 32),
            'amp_s' => fake()->randomFloat(2, 0, 32),
            'amp_t' => fake()->randomFloat(2, 0, 32),
            'note' => null,
            'user_id' => null,
        ];
    }
}
