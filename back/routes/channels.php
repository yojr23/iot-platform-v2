<?php

use App\Models\Sensor;
use App\Models\User;
use App\Services\Security\ResourceAccessService;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Stage 2 public-vs-private decision (PLAN.md 2.3, audit.md open Q#3):
| `sensor.{id}` (App\Events\NewSensorReading) is a plain
| `Illuminate\Broadcasting\Channel` instance, i.e. a PUBLIC channel, where
| `PublicGraphVisibility` allows it: it backs the guest graph dashboard and
| carries no device keys/tokens or private user fields. Public channels never
| hit this file or the `/broadcasting/auth` endpoint, so no entry is required
| or added for them. Do not convert it to PrivateChannel without a real
| non-public field.
|
| Gate 8: `device-status` (App\Events\DeviceStatusUpdated) is NOT public —
| moved from a plain `Channel` to a `PrivateChannel` below, alongside
| `alerts`. Device status, like alerts, is not guest data (PLAN.md /
| audit.md §12a).
|
| Stage 7 (PLAN.md Stage 7, audit.md §12a): `alerts` (App\Events\
| NewAlertTriggered, App\Events\AlertResolved) is NOT public data — PLAN.md
| is explicit that "alerts, events, device status, preferences ... are not
| guest data". It moved from a plain `Channel` to a `PrivateChannel` below.
|
| The default per-user private channel below is standard Laravel scaffolding,
| kept for any future authenticated-only broadcast (e.g. Stage 8 webhook
| delivery status to the owning user). It is not wired to any event yet.
|
| Pre-Stage-6 preflight (private `sensor.{sensorId}` channel): Stage 6 will
| suppress the PUBLIC `sensor.{id}` channel for non-public sensors via
| `PublicGraphVisibility` (PLAN.md 6.0), so authenticated views need a
| private delivery path that already exists before that flip happens. This
| entry authorizes it.
|
| SEC-RT-001: the three closures below (`sensor.{sensorId}`, `alerts`,
| `device-status`) previously hardcoded `true`/a bare `exists()` check with no
| per-user rule at all. They now delegate to `ResourceAccessService` — the
| SAME authority `SensorPolicy`/`DevicePolicy` (SEC-BOLA-001/002) use for the
| REST reads of the same resources — so the WebSocket and REST authorization
| surfaces cannot diverge. Do not reintroduce a hardcoded `true`/`exists()`
| check here; to change the rule (e.g. add lab scoping later), change
| `ResourceAccessService` only.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('sensor.{sensorId}', function (User $user, $sensorId) {
    $sensor = Sensor::query()->find($sensorId);

    if (! $sensor) {
        return false;
    }

    return app(ResourceAccessService::class)->canViewSensor($user, $sensor);
});

Broadcast::channel('alerts', function (User $user) {
    return app(ResourceAccessService::class)->canReceiveAlerts($user);
});

Broadcast::channel('device-status', function (User $user) {
    return app(ResourceAccessService::class)->canReceiveDeviceStatus($user);
});
