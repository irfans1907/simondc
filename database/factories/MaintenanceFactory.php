<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Maintenance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Maintenance>
 */
class MaintenanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Maintenance>
     */
    protected $model = Maintenance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'maintenance_date' => fake()->date(),
            'end_date' => null,
            'result' => fake()->randomElement(Maintenance::RESULTS),
            'evidence_photo' => null,
            'document' => null,
        ];
    }
}
