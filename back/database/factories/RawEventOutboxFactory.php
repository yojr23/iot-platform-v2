<?php

namespace Database\Factories;

use App\Models\RawSensorEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RawEventOutbox>
 */
class RawEventOutboxFactory extends Factory
{
    public function definition(): array
    {
        return [
            'raw_sensor_event_id' => RawSensorEvent::factory(),
            'status' => 'pending',
            'attempts' => 0,
        ];
    }
}
