<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Rack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rack_id' => Rack::factory(),
            'city' => fake()->randomElement(Customer::CITIES),
            'location' => fake()->randomElement(Customer::LOCATIONS),
            'customer_name' => fake()->company(),
            'service_type' => fake()->optional()->randomElement(['Colocation', 'Managed Service']),
            'sales_channel' => fake()->optional()->randomElement(['Direct', 'Partner']),
            'business_status' => fake()->randomElement(Customer::BUSINESS_STATUSES),
            'service_status' => fake()->randomElement(Customer::SERVICE_STATUSES),
            'order_number' => fake()->optional()->numerify('ORD-#####'),
            'sid_rack' => fake()->optional()->numerify('SID-#####'),
            'activation_date' => fake()->optional()->date('Y-m-d'),
            'power_source' => fake()->randomElement(Customer::POWER_SOURCES),
            'connectivity' => fake()->optional()->randomElement(['Fiber', 'Copper']),
            'ip_address' => fake()->optional()->ipv4(),
            'switch_type' => fake()->optional()->words(2, true),
            'port' => fake()->optional()->numerify('#'),
            'vlan' => fake()->optional()->numerify('###'),
            'service_vlan' => fake()->optional()->numerify('###'),
            'account_manager' => fake()->optional()->name(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
