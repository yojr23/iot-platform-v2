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
 * Existing code reused: `User::hasVerifiedEmail()` (Illuminate\Auth\MustVerifyEmail, already the
 * gate for the `verified` route middleware) and `User::$is_admin` (already the sole gate for the
 * `admin` route middleware). Mirrors the shape of `App\Policies\AlertPolicy` (SEC-ALERT-001).
 * Existing owner retired/delegated: `routes/channels.php` previously hardcoded `true` (`alerts`,
 * `device-status`) or a bare `exists()` check (`sensor.{sensorId}`) with no user check at all —
 * this is a net-new authority, not a replacement of prior *logic* (there wasn't any), but it does
 * retire those closures' right to decide the rule themselves.
 * Compatibility window: none needed — behavior for verified users is unchanged; only unverified
 * users lose realtime channel access, matching the REST boundary they already hit.
 *
 * No user-to-lab ownership model exists in this app (`User` has no lab relation). The documented
 * business rule, until one exists, is: any verified authenticated user may read all private
 * telemetry; unverified users may not; admins always may (admin implies verified in practice, but
 * checked explicitly here for clarity, not by construction).
 *
 * To add lab-scoped access later: change ONLY this service (e.g. add a user_lab_access lookup and
 * consult it here) — do not scatter per-resource checks back into controllers or channels.php.
 */
final class ResourceAccessService
{
    public function canViewDevice(User $user, Device $device): bool
    {
        return $user->is_admin || $user->hasVerifiedEmail();
    }

    public function canViewSensor(User $user, Sensor $sensor): bool
    {
        return $user->is_admin || $user->hasVerifiedEmail();
    }

    public function canReceiveAlerts(User $user): bool
    {
        return $user->is_admin || $user->hasVerifiedEmail();
    }

    public function canReceiveDeviceStatus(User $user): bool
    {
        return $user->is_admin || $user->hasVerifiedEmail();
    }
}
