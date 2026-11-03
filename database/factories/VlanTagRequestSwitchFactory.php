<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\VlanTagRequest;
use App\Models\VlanTagRequestSwitch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VlanTagRequestSwitch>
 */
class VlanTagRequestSwitchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vlan_tag_request_id' => VlanTagRequest::factory(),
            'asset_id' => Asset::factory(),
            'port_number' => 'Gi0/'.fake()->numberBetween(1, 48),
        ];
    }
}
