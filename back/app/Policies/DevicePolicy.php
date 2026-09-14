<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;
use App\Services\Security\ResourceAccessService;

/**
 * SEC-BOLA-002: authorization gate for private device reads (show/sensors/index/statusSnapshot in
 * DeviceApiController).
 *
 * RBAC integration: This policy now uses the roles + permissions system.
 * The user must have the appropriate permission for the requested operation.
 */
class DevicePolicy
{
    public function view(User $user, Device $device): bool
    {
        return app(ResourceAccessService::class)->canViewDevice($user, $device);
    }

    public function viewAny(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->can('device.view');
    }
}
