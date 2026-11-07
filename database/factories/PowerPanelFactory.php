<?php

namespace Database\Factories;

use App\Models\PowerPanel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PowerPanel>
 */
class PowerPanelFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PowerPanel>
     */
    protected $model = PowerPanel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'panel_group' => fake()->randomElement(PowerPanel::GROUPS),
            'name' => 'PANEL '.fake()->unique()->bothify('??-##'),
            'capacity' => fake()->randomElement([10, 40, 100, 160, 250, 400, 630, 800, 1250]),
            'unit' => 'AMP',
            'note' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
