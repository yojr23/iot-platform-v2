<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;
use App\Services\Security\ResourceAccessService;

/**
 * SEC-BOLA-002: authorization gate for private device reads (show/sensors/index/statusSnapshot in
 * DeviceApiController).
 *
 * Existing code reused: `ResourceAccessService::canViewDevice()` (SEC-RT-001) — the single
 * visibility authority also consulted by `routes/channels.php`'s `device-status` channel.
 * Existing owner retired/delegated: none — these controller actions previously had no
 * authorization check at all (only `auth:sanctum`+`verified` route middleware).
 * Compatibility window: none — Laravel 12 policy auto-discovery wires this automatically
 * (mirrors `App\Policies\AlertPolicy` / `App\Policies\SensorPolicy`).
 */
class DevicePolicy
{
    public function view(User $user, Device $device): bool
    {
        return app(ResourceAccessService::class)->canViewDevice($user, $device);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }
}
