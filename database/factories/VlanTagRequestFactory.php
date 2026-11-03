<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\VlanTagRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VlanTagRequest>
 */
class VlanTagRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'order_number' => strtoupper(fake()->bothify('ORD-####')),
            'sid' => strtoupper(fake()->bothify('SID-####')),
            'service_name' => fake()->words(3, true),
            'vlan' => (string) fake()->numberBetween(1, 4094),
            'mode' => fake()->randomElement(VlanTagRequest::MODES),
        ];
    }
}
