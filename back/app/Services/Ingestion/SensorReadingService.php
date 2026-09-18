<?php

namespace App\Services\Ingestion;

use App\Models\Sensor;
use App\Models\SensorReading;
use App\Services\Monitoring\PublicGraphVisibility;
use App\Services\ReadingProvenanceService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SensorReadingService
{
    public function __construct(
        private DomainEventRecorder $recorder,
        private SensorReadingProjectionService $projection,
        private ReadingProvenanceService $provenance,
        private PublicGraphVisibility $publicVisibility = new PublicGraphVisibility(),
    ) {
    }

    /**
     * @param  array{raw_sensor_event_id?: int|null, source_key?: string, normalizer_version?: string}|null  $provenance
     */
    public function createReading(
        Sensor $sensor,
        float $value,
        DateTimeInterface|string|null $readingTime = null,
        ?array $provenance = null,
    ): SensorReading
    {
        Log::info('SensorReadingService:createReading entry', [
            'sensor_id' => $sensor->id,
            'value' => $value,
        ]);

        $startTime = microtime(true);

        $result = DB::transaction(function () use ($sensor, $value, $readingTime, $provenance): SensorReading {
            // Attach the already-resolved sensor BEFORE save() so the synchronous `created`
            // observer chain (AlertService::triggeredRulesForReading) reuses it instead of
            // re-querying `sensors` per reading — keeps sensor resolution O(1) per receipt.
            $reading = $sensor->readings()->make([
                'value' => $value,
                'reading_time' => $this->normalizeReadingTime($readingTime),
            ]);
            $reading->setRelation('sensor', $sensor);
            $reading->save();

            $this->provenance->createProjection(
                $reading,
                $provenance['raw_sensor_event_id'] ?? null,
                $provenance['source_key'] ?? 'direct-api',
                $provenance['normalizer_version'] ?? 'v1',
            );

            // Load sub-relations onto the already-attached sensor (sensor_types/devices/labs),
            // without re-selecting `sensors` — the sensor model is already in memory. Done BEFORE
            // recording so the durable event payload can capture the denormalized fields.
            $reading->sensor->loadMissing('sensorType', 'device.lab');

            // P1 (durable self-contained event): capture every immutable fact the broadcast needs —
            // denormalized sensor/device/lab metadata AND the audience decision AT EVENT TIME
            // (`public_at_occurrence`) — so DomainEventBroadcastConsumer can deliver without a
            // SensorReading::find() re-read and without re-evaluating current visibility. This
            // resolves the audit's event-time-vs-delivery-time visibility ambiguity in favor of
            // EVENT-TIME: whether the sensor was public when the reading occurred is what governs
            // the public channel, not whatever the sensor row says at delivery.
            $sensorType = $sensor->sensorType;
            $device = $sensor->device;
            $lab = $device?->lab;

            $this->recorder->record(
                eventType: 'sensor.reading.created',
                aggregateType: 'sensor_reading',
                aggregateId: $reading->id,
                payload: [
                    'reading_id' => $reading->id,
                    'sensor_id' => $sensor->id,
                    'value' => $value,
                    'reading_time' => $reading->reading_time?->toIso8601String(),
                    // Denormalized, immutable-at-event-time delivery fields:
                    'sensor_name' => $sensor->name,
                    'sensor_type' => $sensorType?->name,
                    'unit' => $sensorType?->unit,
                    'device_id' => $device?->id,
                    'device_name' => $device?->name,
                    'lab_id' => $lab?->id,
                    'lab_name' => $lab?->name,
                    'public_at_occurrence' => $this->publicVisibility->isPublic($sensor),
                ],
            );

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
