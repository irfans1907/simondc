<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Troubleshoot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Troubleshoot>
 */
class TroubleshootFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Troubleshoot>
     */
    protected $model = Troubleshoot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'maintenance_id' => null,
            'asset_id' => Asset::factory(),
            'troubleshoot_date' => fake()->date(),
            'started_date' => null,
            'closed_date' => null,
            'result' => fake()->randomElement(Troubleshoot::RESULTS),
            'evidence_photo' => null,
            'document' => null,
        ];
    }
}
