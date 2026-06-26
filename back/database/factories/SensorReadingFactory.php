<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SensorReading>
 */
class SensorReadingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sensor_id' => \App\Models\Sensor::factory(),
            'value' => function (array $attributes) {
                $sensor = \App\Models\Sensor::find($attributes['sensor_id']);
                $sensorType = $sensor?->sensorType;

                $min = $sensorType?->min_range ?? 0;
                $max = $sensorType?->max_range ?? 100;

                return $this->faker->randomFloat(2, $min, $max);
            },
            'reading_time' => $this->faker->unique()->dateTimeThisMonth(),
        ];
    }
}
