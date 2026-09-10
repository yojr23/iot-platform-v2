<?php

namespace App\Services\Monitoring;

use App\Models\Sensor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * PLAN.md Stage 6.0 — sole owner of the public-graph visibility decision.
 *
 * Existing code reused: extends the established `Services/Monitoring/*` ownership pattern
 * (`App\Services\Monitoring\ApiMetricsService`) rather than opening a new telemetry/policy tree.
 * Existing owner retired/delegated: n/a — no prior visibility decision existed anywhere in the
 * codebase; `sensor.status` / `device.status` / `device.is_active` are operational fields owned
 * by `DeviceService`/`SensorApiController` and were never a visibility policy to begin with.
 * Compatibility window: n/a.
 *
 * The rule is exactly `$sensor->public_monitoring_enabled === true`. Never widen this to read
 * status, is_active, or any Device/Lab field — every caller (REST bootstrap, REST series,
 * `DomainEventBroadcastConsumer`) must go through this class so the decision stays single-owned
 * and fail-closed by default (column default is `false`, no bulk-true backfill).
 */
final class PublicGraphVisibility
{
    public function isPublic(Sensor $sensor): bool
    {
        $result = $sensor->public_monitoring_enabled === true;
        Log::info('PublicGraphVisibility:isPublic check', [
            'sensor_id' => $sensor->id,
            'is_public' => $result,
        ]);
        return $result;
    }

    public function publicSensorsQuery(): Builder
    {
        Log::info('PublicGraphVisibility:publicSensorsQuery entry');
        return Sensor::query()->where('public_monitoring_enabled', true);
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function requirePublic(Sensor $sensor): Sensor
    {
        $isPublic = $this->isPublic($sensor);
        if (! $isPublic) {
            Log::warning('PublicGraphVisibility:requirePublic aborting 404', ['sensor_id' => $sensor->id]);
        }
        abort_unless($isPublic, 404);

        return $sensor;
    }
}
