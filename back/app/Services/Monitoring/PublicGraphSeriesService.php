<?php

namespace App\Services\Monitoring;

use App\Models\Sensor;
use App\Models\SensorReading;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * PLAN.md Stage 6.0/6.3 — bounded DB-backed graph series for an already-authorized public sensor.
 *
 * Existing code reused: the `sensor_readings(sensor_id, reading_time, id)` composite index
 * (migration `2026_09_09_000001`) and the timezone-normalization convention established by
 * `App\Services\Ingestion\SensorReadingService::normalizeReadingTime()` — `reading_time` is a
 * naive APP_TIMEZONE wall-clock string, never UTC, so a UTC request window must be converted the
 * same way before it is bound into the query.
 * Existing owner retired/delegated: n/a — no prior bounded-range series query existed;
 * `SensorApiController::readings()`/`latestReadings()` remain the authenticated/legacy paths and
 * are untouched by this service.
 * Compatibility window: n/a.
 *
 * Visibility is enforced by the caller (`PublicGraphController` via `PublicGraphVisibility`)
 * before a `Sensor` ever reaches this class — this service performs no policy checks itself.
 */
final class PublicGraphSeriesService
{
    /**
     * ponytail: illustrative bounded-query ceiling, not a measured one. PLAN.md's Contract Freeze
     * defers the real sample ceiling to a Stage 6 MySQL EXPLAIN benchmark against the composite
     * index; this value only has to be generous enough that a legitimate high-frequency window
     * (e.g. 151 two-second samples across 5 minutes) is never silently truncated. Replace with the
     * measured ceiling once that EXPLAIN evidence exists.
     */
    private const SAMPLE_LIMIT = 5000;

    /**
     * @return array{
     *     window: array{from:string,to:string},
     *     points: list<array{timestamp:string,value:float,reading_id:int}>,
     *     stats: array{min:?float,max:?float,mean:?float,count:int,partial:bool},
     *     truncated: bool,
     *     returned_count: int,
     * }
     */
    public function series(Sensor $sensor, CarbonImmutable $fromUtc, CarbonImmutable $toUtc): array
    {
        Log::info('PublicGraphSeriesService:series entry', [
            'sensor_id' => $sensor->id,
            'from' => $fromUtc->toIso8601String(),
            'to' => $toUtc->toIso8601String(),
        ]);

        $startTime = microtime(true);
        $appTimezone = config('app.timezone');

        $fromLocal = $fromUtc->setTimezone($appTimezone)->format('Y-m-d H:i:s');
        $toLocal = $toUtc->setTimezone($appTimezone)->format('Y-m-d H:i:s');

        /** @var Collection<int, SensorReading> $fetched */
        $fetched = $sensor->readings()
            ->where('reading_time', '>=', $fromLocal)
            ->where('reading_time', '<', $toLocal)
            ->where('reading_time', '<=', now())
            ->orderBy('reading_time')
            ->orderBy('id')
            ->limit(self::SAMPLE_LIMIT + 1)
            ->get(['id', 'value', 'reading_time']);

        $truncated = $fetched->count() > self::SAMPLE_LIMIT;
        $readings = $truncated ? $fetched->take(self::SAMPLE_LIMIT) : $fetched;

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('PublicGraphSeriesService:series completed', [
            'sensor_id' => $sensor->id,
            'fetched_count' => $fetched->count(),
            'truncated' => $truncated,
            'duration_ms' => $durationMs,
        ]);

        if ($durationMs > 100) {
            Log::warning('PublicGraphSeriesService:series slow query', [
                'duration_ms' => $durationMs,
                'table' => 'sensor_readings',
                'sensor_id' => $sensor->id,
            ]);
        }

        return [
            'window' => [
                'from' => $this->toWireFormat($fromUtc),
                'to' => $this->toWireFormat($toUtc),
            ],
            'points' => $this->points($readings),
            'stats' => $this->stats($readings, $truncated),
            'truncated' => $truncated,
            'returned_count' => $readings->count(),
        ];
    }

    /**
     * @param Collection<int, SensorReading> $readings
     * @return list<array{timestamp:string,value:float,reading_id:int}>
     */
    private function points(Collection $readings): array
    {
        return $readings->map(fn (SensorReading $reading) => [
            'timestamp' => $this->toWireFormat($reading->reading_time->clone()->setTimezone('UTC')),
            'value' => (float) $reading->value,
            'reading_id' => $reading->id,
        ])->values()->all();
    }

    /**
     * `partial` = the window held more readings than the returned sample, so count is a count of the
     * RETURNED points, not of the window. Callers must not present partial stats as complete totals.
     *
     * @param Collection<int, SensorReading> $readings
     * @return array{min:?float,max:?float,mean:?float,count:int,partial:bool}
     */
    private function stats(Collection $readings, bool $truncated = false): array
    {
        if ($readings->isEmpty()) {
            return ['min' => null, 'max' => null, 'mean' => null, 'count' => 0, 'partial' => false];
        }

        $values = $readings->map(fn (SensorReading $reading) => (float) $reading->value);

        return [
            'min' => $values->min(),
            'max' => $values->max(),
            'mean' => round($values->avg(), 6),
            'count' => $values->count(),
            'partial' => $truncated,
        ];
    }

    private function toWireFormat(\DateTimeInterface $instant): string
    {
        return CarbonImmutable::instance($instant)->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
    }
}
