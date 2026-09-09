<?php

namespace App\Services\Ingestion;

use App\Models\SensorReading;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SensorReadingProjectionService
{
    private const CACHE_LIMIT = 120;

    public function append(SensorReading $reading): void
    {
        try {
            $payload = json_encode($this->format($reading), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $key = $this->key($reading->sensor_id);

            Redis::pipeline(function ($pipe) use ($key, $payload): void {
                $pipe->lpush($key, $payload);
                $pipe->ltrim($key, 0, self::CACHE_LIMIT - 1);
            });
        } catch (Throwable $e) {
            Log::warning('Redis cache update skipped for latest readings', [
                'sensor_id' => $reading->sensor_id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return Collection<int, array{id:int,value:float,reading_time:?string,created_at:?string}>|null
     */
    public function latest(int $sensorId, int $limit): ?Collection
    {
        try {
            $rawEntries = Redis::lrange($this->key($sensorId), 0, $limit - 1);

            if (! is_array($rawEntries) || $rawEntries === []) {
                return collect();
            }

            $readings = collect($rawEntries)->map(function ($entry): ?array {
                if (! is_string($entry)) {
                    return null;
                }

                $decoded = json_decode($entry, true);

                if (! is_array($decoded) || ! isset($decoded['id'], $decoded['value'])) {
                    return null;
                }

                return [
                    'id' => (int) $decoded['id'],
                    'value' => (float) $decoded['value'],
                    'reading_time' => $decoded['reading_time'] ?? null,
                    'created_at' => $decoded['created_at'] ?? null,
                ];
            });

            return $readings->contains(null) ? null : $readings->values();
        } catch (Throwable $e) {
            Log::warning('Redis lookup failed for latest sensor readings', [
                'sensor_id' => $sensorId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param Collection<int, SensorReading|array{id:int,value:float,reading_time:?string,created_at:?string}> $readings
     */
    public function warm(int $sensorId, Collection $readings): void
    {
        try {
            $serialized = $readings
                ->map(fn (SensorReading|array $reading): string => json_encode(
                    $this->format($reading),
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ))
                ->values()
                ->all();

            $key = $this->key($sensorId);
            Redis::pipeline(function ($pipe) use ($key, $serialized): void {
                $pipe->del($key);

                foreach (array_reverse($serialized) as $payload) {
                    $pipe->lpush($key, $payload);
                }

                $pipe->ltrim($key, 0, self::CACHE_LIMIT - 1);
            });
        } catch (Throwable $e) {
            Log::warning('Redis cache warm-up skipped for latest readings', [
                'sensor_id' => $sensorId,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{id:int,value:float,reading_time:?string,created_at:?string}
     */
    public function format(SensorReading|array $reading): array
    {
        if ($reading instanceof SensorReading) {
            return [
                'id' => $reading->id,
                'value' => (float) $reading->value,
                'reading_time' => $reading->reading_time?->toIso8601String(),
                'created_at' => $reading->created_at?->toIso8601String(),
            ];
        }

        return [
            'id' => (int) $reading['id'],
            'value' => (float) $reading['value'],
            'reading_time' => $reading['reading_time'] ?? null,
            'created_at' => $reading['created_at'] ?? null,
        ];
    }

    private function key(int $sensorId): string
    {
        return "sensor:latest_readings:{$sensorId}";
    }
}
