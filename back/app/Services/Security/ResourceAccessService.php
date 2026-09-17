<?php

namespace App\Services\Security;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;

/**
 * SEC-BOLA-001 / SEC-BOLA-002 / SEC-RT-001: the SINGLE source of the private-telemetry visibility
 * rule for this app. Both REST policies (`SensorPolicy`, `DevicePolicy`) and broadcast channel
 * closures (`routes/channels.php`) delegate here so the two authorization surfaces (HTTP + Pusher
 * `/broadcasting/auth`) cannot diverge.
 *
 * RBAC integration: This service now uses the roles + permissions system instead of is_admin.
 * The user must have the appropriate permission for the requested resource.
 *
 * No user-to-lab ownership model exists in this app (`User` has no lab relation). The documented
 * business rule, until one exists, is: any verified authenticated user with the appropriate
 * permission may read private telemetry; unverified users may not; users with the required
 * permissions may access resources.
 *
 * To add lab-scoped access later: change ONLY this service (e.g. add a user_lab_access lookup and
 * consult it here) — do not scatter per-resource checks back into controllers or channels.php.
 */
final class ResourceAccessService
{
    public function canViewDevice(User $user, Device $device): bool
    {
        return $user->hasVerifiedEmail() && $user->can('device.view');
    }

    public function canViewSensor(User $user, Sensor $sensor): bool
    {
        return $user->hasVerifiedEmail() && $user->can('sensor.view');
    }

    /**
     * SEC-RT-002: telemetry (reading value/time) authority. This is the SINGLE rule the REST reading
     * endpoints (`permission:sensor_reading.view`) and the telemetry-carrying `sensor.{id}` broadcast
     * channel must both delegate to. Keeping it separate from `canViewSensor` (metadata-only) prevents
     * a WS/REST divergence where `sensor.view` alone would leak live readings that REST denies.
     */
    public function canViewSensorReadings(User $user, Sensor $sensor): bool
    {
        return $user->hasVerifiedEmail() && $user->can('sensor_reading.view');
    }

    public function canReceiveAlerts(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->can('alert.view');
    }

    public function canReceiveDeviceStatus(User $user): bool
    {
        return $user->hasVerifiedEmail() && $user->can('device.view');
    }
}
