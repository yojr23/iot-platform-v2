<?php

namespace App\Services\Ingestion;

use App\Models\Device;
use App\Models\RawSensorEvent;
use App\Models\Sensor;
use Illuminate\Support\Facades\Log;
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
        $nodeId = $event->node_id;
        $device = $nodeId !== null && $nodeId !== ''
            ? Device::query()->where('serial_number', $nodeId)->first()
            : null;

        if (! $device) {
            throw new RuntimeException("RawReadingNormalizer: no device found for node_id [{$nodeId}]");
        }

        $sensorsPayload = (array) data_get($event->payload, 'sensors', []);
        $readingTime = data_get($event->payload, 'timestamp') ?? $event->received_at ?? now();

        $created = 0;
        $skipped = [];

        foreach ($sensorsPayload as $key => $entry) {
            $value = data_get($entry, 'value');

            // Strict validation at the trust boundary (PLAN.md instructions): reject and log
            // rather than silently accepting a non-numeric/malformed sensor value.
            if (! is_numeric($value)) {
                $skipped[] = (string) $key;
                Log::warning('RawReadingNormalizer: rejected non-numeric sensor value', [
                    'raw_sensor_event_id' => $event->id,
                    'sensor_key' => $key,
                    'value' => $value,
                ]);

                continue;
            }

            $sensor = Sensor::query()
                ->where('device_id', $device->id)
                ->whereRaw('LOWER(name) = ?', [strtolower((string) $key)])
                ->first();

            if (! $sensor) {
                $skipped[] = (string) $key;
                Log::warning('RawReadingNormalizer: no sensor mapped for payload key', [
                    'raw_sensor_event_id' => $event->id,
                    'device_id' => $device->id,
                    'sensor_key' => $key,
                ]);

                continue;
            }

            $this->readingService->createReading($sensor, (float) $value, $readingTime);

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
