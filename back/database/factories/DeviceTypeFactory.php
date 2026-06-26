<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DeviceType>
 */
class DeviceTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
            'Temperature Sensor',
            'Humidity Sensor',
            'Light Sensor',
            'Motion Sensor',
            'Air Quality Sensor',
            'Pressure Sensor',
            'Sound Sensor',
        ];
        return [
            'name' => $this->faker->unique()->word(), // Generar nombres únicos
            'description' => $this->faker->sentence(),
        ];
    }
}
