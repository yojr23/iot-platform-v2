<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sensor;
use App\Services\Monitoring\PublicGraphSeriesService;
use App\Services\Monitoring\PublicGraphVisibility;
use App\Services\Monitoring\RuleToGraphZones;
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
 * `PublicGraphSeriesService` for the bounded DB-backed range query. graph-semantic-zones-plan.md
 * (GRAPH-002): `bootstrap` now also projects `RuleToGraphZones::zonesFor()` per public sensor,
 * reduced to `{from,to,severity}` only — no rule ids/scope/notification policy leak to guests.
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

    public function bootstrap(PublicGraphVisibility $visibility, RuleToGraphZones $zones): JsonResponse
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
            ->map(function ($sensorsForDevice) use ($zones) {
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
                        'bands' => $this->publicBands($zones, $sensor),
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

        // JSON_PRESERVE_ZERO_FRACTION: without it, an integer-valued band boundary like 30.0
        // would serialize as the int 30, silently losing the "measured threshold" float contract
        // (same reasoning as `series()` below).
        return response()->json([
            'version' => 1,
            'default_sensor_id' => $sensors->first()?->id,
            'devices' => $devices->all(),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
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

        // JSON_PRESERVE_ZERO_FRACTION: without it, PHP's json_encode (serialize_precision=-1)
        // renders an integer-valued float like 10.0 as "10", so a client (or this suite's
        // assertSame(10.0, ...)) would receive/decode an int and silently lose the "this is a
        // measured float" contract for point values and stats.
        return response()->json($result, 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Public-safe reduction of `RuleToGraphZones::zonesFor()`: only `{from,to,severity}` per band.
     * No rule ids, no scope, no notification policy — guests never see anything beyond the visual
     * severity intervals needed to paint the plot area.
     *
     * @return list<array{from: float|null, to: float|null, severity: string}>
     */
    private function publicBands(RuleToGraphZones $zones, Sensor $sensor): array
    {
        return array_map(
            fn (array $zone) => ['from' => $zone['from'], 'to' => $zone['to'], 'severity' => $zone['severity']],
            $zones->zonesFor($sensor)['zones'],
        );
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
