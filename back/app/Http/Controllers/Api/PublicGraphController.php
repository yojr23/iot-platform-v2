<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sensor;
use App\Services\Monitoring\PublicGraphSeriesService;
use App\Services\Monitoring\PublicGraphVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * PLAN.md Stage 6.0 — the only anonymous product-domain REST surface this task adds: a minimal
 * graph bootstrap and a bounded graph series, both gated by `PublicGraphVisibility`.
 *
 * Existing code reused: `PublicGraphVisibility` (this task's policy owner), the `Sensor` /
 * `SensorType` / `Device` relations already defined on the models, and
 * `PublicGraphSeriesService` for the bounded DB-backed range query.
 * Existing owner retired/delegated: none — per PLAN.md v1.3 correction #4, transitional public
 * routes (`/api/dashboard/public`, `/api/sensors/{sensor}/latest-readings`,
 * `/api/devices/{device}/sensors`, `/api/config/public`) stay reachable until the atomic
 * cutover step; this controller only adds the new graph-only surface alongside them.
 * Compatibility window: n/a for this controller; the transitional routes' removal is a later,
 * separate task.
 */
class PublicGraphController extends Controller
{
    private const TIMESTAMP_FORMAT = 'Y-m-d\TH:i:s\Z';

    public function bootstrap(PublicGraphVisibility $visibility): JsonResponse
    {
        $startTime = microtime(true);

        $sensors = $visibility->publicSensorsQuery()
            ->with(['sensorType', 'device'])
            ->orderBy('device_id')
            ->orderBy('id')
            ->get()
            ->filter(fn (Sensor $sensor) => $sensor->device !== null)
            ->values();

        $devices = $sensors
            ->groupBy('device_id')
            ->map(function ($sensorsForDevice) {
                $device = $sensorsForDevice->first()->device ?? null;

                if ($device === null) {
                    return null;
                }

                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'sensors' => $sensorsForDevice->map(fn (Sensor $sensor) => [
                        'id' => $sensor->id,
                        'name' => $sensor->name,
                        'unit' => $sensor->sensorType?->unit,
                    ])->values()->all(),
                ];
            })
            ->filter()
            ->values();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('PublicGraph bootstrap request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'sensor_count' => $sensors->count(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'version' => 1,
            'default_sensor_id' => $sensors->first()?->id,
            'devices' => $devices->all(),
        ]);
    }

    public function series(
        Request $request,
        Sensor $sensor,
        PublicGraphVisibility $visibility,
        PublicGraphSeriesService $service,
    ): JsonResponse {
        $startTime = microtime(true);

        $visibility->requirePublic($sensor);

        [$from, $to] = $this->validatedWindow($request);

        $result = $service->series($sensor, $from, $to);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('PublicGraph series request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'sensor_id' => $sensor->id,
            'duration_ms' => $durationMs,
        ]);

        return response()->json($result);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function validatedWindow(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/'],
            'to' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/'],
        ]);

        $from = CarbonImmutable::createFromFormat(self::TIMESTAMP_FORMAT, $validated['from'], 'UTC') ?: null;
        $to = CarbonImmutable::createFromFormat(self::TIMESTAMP_FORMAT, $validated['to'], 'UTC') ?: null;

        if (! $from || ! $to || ! $from->lessThan($to)) {
            throw ValidationException::withMessages([
                'range' => 'The graph window requires from < to using YYYY-MM-DDTHH:mm:ssZ.',
            ]);
        }

        return [$from, $to];
    }
}
