<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RawSensorEvent>
 */
class RawSensorEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'topic' => 'iot/'.$this->faker->slug().'/readings',
            'source' => 'ingestion_service',
            'source_event_id' => $this->faker->unique()->uuid(),
            'node_id' => $this->faker->slug(),
            'payload' => [
                'sensors' => [
                    'temperature' => ['value' => $this->faker->randomFloat(2, 10, 40)],
                ],
            ],
            'received_at' => now(),
            'status' => 'received',
        ];
    }
}
