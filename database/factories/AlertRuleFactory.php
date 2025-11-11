<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AlertRule>
 */
class AlertRuleFactory extends Factory
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
            'sensor_type_id' => function (array $attributes) {
                $sensor = \App\Models\Sensor::find($attributes['sensor_id']);
                return $sensor->sensor_type_id;
            },
            'device_id' => function (array $attributes) {
                $sensor = \App\Models\Sensor::find($attributes['sensor_id']);
                return $sensor->device_id;
            },
            'min_value' => function (array $attributes) {
                $sensor = \App\Models\Sensor::find($attributes['sensor_id']);
                return $sensor->sensorType->min_range ?? 0;
            },
            'max_value' => function (array $attributes) {
                $sensor = \App\Models\Sensor::find($attributes['sensor_id']);
                return $sensor->sensorType->max_range ?? 100;
            },
            'severity' => $this->faker->randomElement(['info', 'warning', 'danger']),
            'message' => $this->faker->unique()->sentence(),
            'name' => $this->faker->words(3, true),
        ];
    }
}
