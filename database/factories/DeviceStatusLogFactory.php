<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceStatusLog>
 */
class DeviceStatusLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => \App\Models\Device::factory(),
            'status' => $this->faker->boolean,
            'changed_at' => $this->faker->dateTimeThisMonth,
        ];
    }
}
