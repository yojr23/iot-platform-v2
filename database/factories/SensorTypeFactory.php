<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SensorType>
 */
class SensorTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sensorTypes = [
            ['name' => 'Temperatura', 'unit' => '°C', 'min_range' => -10, 'max_range' => 50],
            ['name' => 'Humedad', 'unit' => '%', 'min_range' => 0, 'max_range' => 100],
            ['name' => 'Monóxido de Carbono', 'unit' => 'ppm', 'min_range' => 0, 'max_range' => 1000],
            ['name' => 'Componentes Orgánicos', 'unit' => 'ppm', 'min_range' => 0, 'max_range' => 500],
            ['name' => 'Humo', 'unit' => 'ppm', 'min_range' => 0, 'max_range' => 1000],
            ['name' => 'Oxígeno', 'unit' => '%', 'min_range' => 0, 'max_range' => 25],
            ['name' => 'Vibración', 'unit' => 'g', 'min_range' => 0, 'max_range' => 16],
        ];

        $type = $this->faker->randomElement($sensorTypes);

        return [
            'name' => $this->faker->unique()->word(), // Generar nombres únicos
            'unit' => $this->faker->randomElement(['°C', '%', 'ppm', 'g']),
            'min_range' => $this->faker->numberBetween(-10, 0),
            'max_range' => $this->faker->numberBetween(1, 100),
        ];
    }
}
