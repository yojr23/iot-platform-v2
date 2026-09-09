<?php

namespace App\Services\Monitoring;

use App\Models\Sensor;
use Illuminate\Database\Eloquent\Builder;

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
        return $sensor->public_monitoring_enabled === true;
    }

    public function publicSensorsQuery(): Builder
    {
        return Sensor::query()->where('public_monitoring_enabled', true);
    }

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function requirePublic(Sensor $sensor): Sensor
    {
        abort_unless($this->isPublic($sensor), 404);

        return $sensor;
    }
}
