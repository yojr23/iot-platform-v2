<?php

namespace App\Services\Ingestion;

use App\Models\Sensor;
use App\Models\SensorReading;
use Illuminate\Support\Facades\Log;
use Throwable;

class SensorReadingService
{
    public function __construct(
        private DomainEventRecorder $recorder,
        private string $redisKeyPrefix = 'sensor:latest_readings:',
        private int $redisCacheLimit = 120,
    ) {
    }

    public function createReading(Sensor $sensor, float $value, ?string $readingTime = null): SensorReading
    {
        return \DB::transaction(function () use ($sensor, $value, $readingTime): SensorReading {
            $reading = $sensor->readings()->create([
                'value' => $value,
                'reading_time' => $readingTime ?? now(),
            ]);

            $this->recorder->record(
                eventType: 'sensor.reading.created',
                aggregateType: 'sensor_reading',
                aggregateId: $reading->id,
                payload: [
                    'reading_id' => $reading->id,
                    'sensor_id' => $sensor->id,
                    'value' => $value,
                    'reading_time' => $reading->reading_time?->toIso8601String(),
                ],
            );

            return $reading->load('sensor.sensorType', 'sensor.device.lab');
        });
    }
}