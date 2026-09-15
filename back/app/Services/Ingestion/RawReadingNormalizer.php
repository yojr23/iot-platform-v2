<?php

namespace App\Services\Ingestion;

use App\Models\Device;
use App\Models\RawSensorEvent;
use App\Services\SensorMappingService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * PLAN.md Stage 3.3 — normalizes a raw receipt (`RawSensorEvent.payload`) into `SensorReading`
 * rows.
 *
 * Existing code reused: `SensorReadingService::createReading()` — the exact same creation owner
 * `SensorApiController::store()` already uses, so creating a reading here fires the existing
 * `SensorReadingObserver` -> `AlertService::createAlertsForReading()` chain unchanged. This class
 * never evaluates alert rules itself (G0D row B1: AlertService stays the sole rule-evaluation
 * owner).
 * Existing owner retired/delegated: none — this is a new normalization step for a pipeline
 * (async raw ingestion -> readings) that had no reading-creation path before Stage 3.
 * Compatibility window: n/a.
 *
 * Device identity still comes from `node_id`, while measurement identity is resolved only through
 * `SensorMappingService`. This makes aliases and historic key handovers explicit instead of
 * silently coupling raw payload keys to mutable sensor names.
 */
class RawReadingNormalizer
{
    public function __construct(
        private SensorReadingService $readingService,
        private SensorMappingService $mappingService,
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
        $source = $event->source ?: 'ingestion_service';
        $externalKeys = array_values(array_unique(array_map(
            fn ($key): string => $this->canonicalSensorName((string) $key),
            array_keys($sensorsPayload),
        )));
        $sensorsByExternalKey = $this->mappingService->findSensorsByExternalKeys(
            $device,
            $source,
            $externalKeys,
            $this->lookupTime($readingTime),
        );

        $created = 0;
        $skipped = [];
        $processedSourceKeys = [];

        foreach ($sensorsPayload as $key => $entry) {
            $sourceKey = $this->canonicalSensorName((string) $key);

            if (isset($processedSourceKeys[$sourceKey])) {
                continue;
            }

            $processedSourceKeys[$sourceKey] = true;
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

            $sensor = $sensorsByExternalKey->get($sourceKey);

            if (! $sensor) {
                $skipped[] = (string) $key;
                Log::warning('RawReadingNormalizer:no sensor mapped for payload key', [
                    'raw_sensor_event_id' => $event->id,
                    'device_id' => $device->id,
                    'sensor_key' => $key,
                ]);

                continue;
            }

            $this->readingService->createReading($sensor, (float) $value, $readingTime, [
                'raw_sensor_event_id' => $event->id,
                'source_key' => $sourceKey,
                'normalizer_version' => 'v1',
            ]);

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

    private function lookupTime(DateTimeInterface|string $readingTime): DateTimeInterface
    {
        $appTimezone = config('app.timezone');

        if ($readingTime instanceof DateTimeInterface) {
            return Carbon::instance($readingTime)->setTimezone($appTimezone);
        }

        return Carbon::parse($readingTime, $appTimezone)->setTimezone($appTimezone);
    }
}
