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
            $startTime = microtime(true);
            $payload = json_encode($this->format($reading), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $key = $this->key($reading->sensor_id);

            Redis::pipeline(function ($pipe) use ($key, $payload): void {
                $pipe->lpush($key, $payload);
                $pipe->ltrim($key, 0, self::CACHE_LIMIT - 1);
            });

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('SensorReadingProjectionService:append completed', [
                'sensor_id' => $reading->sensor_id,
                'reading_id' => $reading->id,
                'key' => $key,
                'duration_ms' => $durationMs,
            ]);

            if ($durationMs > 100) {
                Log::warning('SensorReadingProjectionService:append slow Redis pipeline', ['duration_ms' => $durationMs, 'key' => $key]);
            }
        } catch (Throwable $e) {
            Log::error('Redis cache update skipped for latest readings', [
                'sensor_id' => $reading->sensor_id,
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);
        }
    }

    /**
     * @return Collection<int, array{id:int,value:float,reading_time:?string,created_at:?string}>|null
     */
    public function latest(int $sensorId, int $limit): ?Collection
    {
        try {
            $startTime = microtime(true);
            $key = $this->key($sensorId);
            $rawEntries = Redis::lrange($key, 0, $limit - 1);

            $cacheHit = is_array($rawEntries) && $rawEntries !== [];
            Log::info('SensorReadingProjectionService:latest cache check', [
                'sensor_id' => $sensorId,
                'cache_hit' => $cacheHit,
                'key' => $key,
            ]);

            if (! is_array($rawEntries) || $rawEntries === []) {
                $durationMs = round((microtime(true) - $startTime) * 1000, 2);
                Log::info('SensorReadingProjectionService:latest completed (empty)', ['sensor_id' => $sensorId, 'duration_ms' => $durationMs]);
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

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            $result = $readings->contains(null) ? null : $readings->values();

            Log::info('SensorReadingProjectionService:latest completed', [
                'sensor_id' => $sensorId,
                'count' => $result?->count() ?? 0,
                'duration_ms' => $durationMs,
            ]);

            if ($durationMs > 100) {
                Log::warning('SensorReadingProjectionService:latest slow Redis lookup', ['duration_ms' => $durationMs, 'key' => $key]);
            }

            return $result;
        } catch (Throwable $e) {
            Log::warning('Redis lookup failed for latest sensor readings', [
                'sensor_id' => $sensorId,
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
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
            $startTime = microtime(true);
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

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('SensorReadingProjectionService:warm completed', [
                'sensor_id' => $sensorId,
                'readings_count' => count($serialized),
                'key' => $key,
                'duration_ms' => $durationMs,
            ]);

            if ($durationMs > 100) {
                Log::warning('SensorReadingProjectionService:warm slow Redis pipeline', ['duration_ms' => $durationMs, 'key' => $key]);
            }
        } catch (Throwable $e) {
            Log::error('Redis cache warm-up skipped for latest readings', [
                'sensor_id' => $sensorId,
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
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
