<?php

namespace App\Services\Ingestion;

use App\Models\Device;
use App\Models\RawSensorEvent;
use App\Models\Sensor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PLAN.md Stage 3.3 — normalizes a raw receipt (`RawSensorEvent.payload`) into `SensorReading`
 * rows.
 *
 * Existing code reused: `Sensor::readings()->create()` — the exact same reading-creation call
 * `SensorApiController::store()` already uses, so creating a reading here fires the existing
 * `SensorReadingObserver` -> `AlertService::createAlertsForReading()` chain unchanged. This class
 * never evaluates alert rules itself (G0D row B1: AlertService stays the sole rule-evaluation
 * owner).
 * Existing owner retired/delegated: none — this is a new normalization step for a pipeline
 * (async raw ingestion -> readings) that had no reading-creation path before Stage 3.
 * Compatibility window: n/a.
 *
 * ponytail: node_id/sensor-key -> Device/Sensor resolution is an exact (case-insensitive) match
 * against the existing `devices.serial_number` and `sensors.name` columns. There is no
 * node/channel mapping table anywhere in this codebase to reuse or extend. Ceiling: a renamed
 * sensor or an aliased payload key silently skips that one reading (logged), it does not fail the
 * whole receipt. Add a real mapping table if/when key aliasing or ambiguous names become a real
 * problem.
 */
class RawReadingNormalizer
{
    public function __construct(
        private SensorReadingService $readingService,
    ) {
    }

    /**
     * @return array{created:int,skipped:list<string>}
     */
    public function normalize(RawSensorEvent $event): array
    {
        Log::info('RawReadingNormalizer:normalize entry', [
            'raw_sensor_event_id' => $event->id,
            'node_id' => $event->node_id,
        ]);

        $qcValid = data_get($event->payload, 'qc.valid');
        if ($qcValid === false) {
            $sensorKeys = array_keys((array) data_get($event->payload, 'sensors', []));

            Log::warning('RawReadingNormalizer: QC-invalid receipt skipped', [
                'raw_sensor_event_id' => $event->id,
                'sensor_keys' => $sensorKeys,
            ]);

            return ['created' => 0, 'skipped' => array_map('strval', $sensorKeys)];
        }

        $startTime = microtime(true);
        $nodeId = $event->node_id;
        $device = $nodeId !== null && $nodeId !== ''
            ? Device::query()->where('serial_number', $nodeId)->first()
            : null;

        if (! $device) {
            Log::warning('RawReadingNormalizer:normalize no device found', ['node_id' => $nodeId]);
            throw new RuntimeException("RawReadingNormalizer: no device found for node_id [{$nodeId}]");
        }

        $sensorsPayload = (array) data_get($event->payload, 'sensors', []);
        $readingTime = data_get($event->payload, 'timestamp') ?? $event->received_at ?? now();

        $groupedSensors = $device->sensors()
            // sensor_type_id needed downstream by AlertService::applicableRules (reused in-memory).
            ->get(['id', 'device_id', 'name', 'sensor_type_id'])
            ->groupBy(fn (Sensor $sensor): string => $this->canonicalSensorName($sensor->name));

        $ambiguousNames = $groupedSensors
            ->filter(fn ($sensors): bool => $sensors->count() > 1)
            ->keys();

        if ($ambiguousNames->isNotEmpty()) {
            throw new RuntimeException(
                'RawReadingNormalizer: ambiguous sensor names for device ['.$device->id.']: '.$ambiguousNames->implode(', ')
            );
        }

        $sensorsByName = $groupedSensors->map(fn ($sensors): Sensor => $sensors->first());

        $created = 0;
        $skipped = [];

        foreach ($sensorsPayload as $key => $entry) {
            $value = data_get($entry, 'value');

            if (! is_numeric($value)) {
                $skipped[] = (string) $key;
                Log::warning('RawReadingNormalizer:rejected non-numeric sensor value', [
                    'raw_sensor_event_id' => $event->id,
                    'sensor_key' => $key,
                    'value' => $value,
                ]);

                continue;
            }

            $sensor = $sensorsByName->get($this->canonicalSensorName((string) $key));

            if (! $sensor) {
                $skipped[] = (string) $key;
                Log::warning('RawReadingNormalizer:no sensor mapped for payload key', [
                    'raw_sensor_event_id' => $event->id,
                    'device_id' => $device->id,
                    'sensor_key' => $key,
                ]);

                continue;
            }

            $this->readingService->createReading($sensor, (float) $value, $readingTime);

            $created++;
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('RawReadingNormalizer:normalize completed', [
            'raw_sensor_event_id' => $event->id,
            'created' => $created,
            'skipped' => count($skipped),
            'duration_ms' => $durationMs,
        ]);

        if ($durationMs > 100) {
            Log::warning('RawReadingNormalizer:normalize slow execution', ['duration_ms' => $durationMs, 'raw_sensor_event_id' => $event->id]);
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function canonicalSensorName(string $name): string
    {
        return Str::lower(trim($name));
    }
}
