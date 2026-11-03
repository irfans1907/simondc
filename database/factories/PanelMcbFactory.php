<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\PanelMcb;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PanelMcb>
 */
class PanelMcbFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'mcb_number' => fake()->unique()->numerify('MCB-##'),
        ];
    }
}
