<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sensor>
 */
class SensorFactory extends Factory
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
            'sensor_type_id' => \App\Models\SensorType::factory(),
            'name' => $this->faker->unique()->word . ' Sensor', // Generar nombres únicos
            'status' => $this->faker->boolean(90),
        ];
    }
}
