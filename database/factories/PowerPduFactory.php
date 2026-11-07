<?php

namespace Database\Factories;

use App\Models\PowerPdu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PowerPdu>
 */
class PowerPduFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PowerPdu>
     */
    protected $model = PowerPdu::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $floor = fake()->randomElement([2, 4]);

        return [
            'floor' => $floor,
            'code' => 'PDU.'.$floor.'-'.fake()->unique()->bothify('?##'),
            'name' => 'PDU '.fake()->word(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
