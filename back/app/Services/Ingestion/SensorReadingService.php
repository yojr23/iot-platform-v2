<?php

namespace App\Services\Ingestion;

use App\Models\Sensor;
use App\Models\SensorReading;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SensorReadingService
{
    public function __construct(
        private DomainEventRecorder $recorder,
        private SensorReadingProjectionService $projection,
    ) {
    }

    public function createReading(Sensor $sensor, float $value, DateTimeInterface|string|null $readingTime = null): SensorReading
    {
        Log::info('SensorReadingService:createReading entry', [
            'sensor_id' => $sensor->id,
            'value' => $value,
        ]);

        $startTime = microtime(true);

        $result = DB::transaction(function () use ($sensor, $value, $readingTime): SensorReading {
            // Attach the already-resolved sensor BEFORE save() so the synchronous `created`
            // observer chain (AlertService::triggeredRulesForReading) reuses it instead of
            // re-querying `sensors` per reading — keeps sensor resolution O(1) per receipt.
            $reading = $sensor->readings()->make([
                'value' => $value,
                'reading_time' => $this->normalizeReadingTime($readingTime),
            ]);
            $reading->setRelation('sensor', $sensor);
            $reading->save();

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

            // Load sub-relations onto the already-attached sensor (sensor_types/devices/labs),
            // without re-selecting `sensors` — the sensor model is already in memory.
            $reading->sensor->loadMissing('sensorType', 'device.lab');

            DB::afterCommit(fn () => $this->projection->append($reading));

            return $reading;
        });

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('SensorReadingService:createReading completed', [
            'reading_id' => $result->id,
            'sensor_id' => $sensor->id,
            'duration_ms' => $durationMs,
        ]);

        if ($durationMs > 100) {
            Log::warning('SensorReadingService:createReading slow transaction', ['duration_ms' => $durationMs, 'sensor_id' => $sensor->id]);
        }

        return $result;
    }

    /**
     * Single write-time normalization owner for `reading_time` (Pre-Stage-6 Task 1 —
     * see docs/implementation/reading-time-semantics.md). Eloquent's `datetime` cast treats a
     * `DateTimeInterface` and a raw string differently (preserves the instance's own tz vs.
     * parsing the string in `date_default_timezone_get()`), so letting the caller's PHP type pick
     * the branch made storage semantics ambiguous. This method always hands Eloquent one
     * unambiguous APP_TIMEZONE wall-clock string:
     * - null -> now() in APP_TIMEZONE.
     * - DateTimeInterface -> same instant, converted to APP_TIMEZONE.
     * - offsetless "Y-m-d H:i:s" -> interpreted AS an APP_TIMEZONE wall clock (legacy behavior).
     * - RFC3339 with `Z`/explicit offset -> parsed as that instant, converted to APP_TIMEZONE.
     * `Carbon::parse($string, $appTimezone)` already implements the string branch correctly on
     * its own: PHP ignores the second (default-timezone) argument whenever the string carries its
     * own UTC offset/`Z`, and honors it otherwise — so one call covers both string cases.
     */
    private function normalizeReadingTime(DateTimeInterface|string|null $readingTime): string
    {
        $appTimezone = config('app.timezone');

        if ($readingTime === null) {
            return Carbon::now($appTimezone)->format('Y-m-d H:i:s');
        }

        if ($readingTime instanceof DateTimeInterface) {
            return Carbon::instance($readingTime)->setTimezone($appTimezone)->format('Y-m-d H:i:s');
        }

        return Carbon::parse($readingTime, $appTimezone)->setTimezone($appTimezone)->format('Y-m-d H:i:s');
    }
}
