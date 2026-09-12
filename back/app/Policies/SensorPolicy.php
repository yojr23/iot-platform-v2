<?php

namespace App\Policies;

use App\Models\Sensor;
use App\Models\User;
use App\Services\Security\ResourceAccessService;

/**
 * SEC-BOLA-001: authorization gate for private sensor reads (show/readings/series/latestReadings/
 * exportReadings/graphZones in SensorApiController).
 *
 * Existing code reused: `ResourceAccessService::canViewSensor()` (SEC-RT-001) — the single
 * visibility authority also consulted by `routes/channels.php`'s `sensor.{sensorId}` channel.
 * Existing owner retired/delegated: none — these controller actions previously had no
 * authorization check at all (only `auth:sanctum`+`verified` route middleware).
 * Compatibility window: none — Laravel 12 policy auto-discovery wires this automatically
 * (mirrors `App\Policies\AlertPolicy`, this app has no AuthServiceProvider).
 */
class SensorPolicy
{
    public function view(User $user, Sensor $sensor): bool
    {
        return app(ResourceAccessService::class)->canViewSensor($user, $sensor);
    }
}
