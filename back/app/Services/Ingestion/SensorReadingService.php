<?php

namespace App\Services\Ingestion;

use App\Models\Sensor;
use App\Models\SensorReading;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class SensorReadingService
{
    public function __construct(
        private DomainEventRecorder $recorder,
        private SensorReadingProjectionService $projection,
    ) {
    }

    public function createReading(Sensor $sensor, float $value, DateTimeInterface|string|null $readingTime = null): SensorReading
    {
        return DB::transaction(function () use ($sensor, $value, $readingTime): SensorReading {
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

            $reading->load('sensor.sensorType', 'sensor.device.lab');

            DB::afterCommit(fn () => $this->projection->append($reading));

            return $reading;
        });
    }
}
