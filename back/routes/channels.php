<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Stage 2 public-vs-private decision (PLAN.md 2.3, audit.md open Q#3):
| `sensor.{id}` and `device-status` (App\Events\NewSensorReading,
| DeviceStatusUpdated) are plain `Illuminate\Broadcasting\Channel` instances,
| i.e. PUBLIC channels. `sensor.{id}` is intentionally public where
| `PublicGraphVisibility` allows it: it backs the guest graph dashboard and
| carries no device keys/tokens or private user fields. Public channels never
| hit this file or the `/broadcasting/auth` endpoint, so no entry is required
| or added for them. Do not convert them to PrivateChannel without a real
| non-public field.
|
| Stage 7 (PLAN.md Stage 7, audit.md §12a): `alerts` (App\Events\
| NewAlertTriggered, App\Events\AlertResolved) is NOT public data — PLAN.md
| is explicit that "alerts, events, device status, preferences ... are not
| guest data". It moved from a plain `Channel` to a `PrivateChannel` below.
| There is no per-alert ACL model in this app (see `AuthApiController` token
| abilities: `*` for admin, `read` for everyone else — same shape as the
| `sensor.{sensorId}` authorization below), so any authenticated user may
| subscribe; the authorization boundary is "authenticated at all", not a
| per-alert scope.
|
| The default per-user private channel below is standard Laravel scaffolding,
| kept for any future authenticated-only broadcast (e.g. Stage 8 webhook
| delivery status to the owning user). It is not wired to any event yet.
|
| Pre-Stage-6 preflight (private `sensor.{sensorId}` channel): Stage 6 will
| suppress the PUBLIC `sensor.{id}` channel for non-public sensors via
| `PublicGraphVisibility` (PLAN.md 6.0), so authenticated views need a
| private delivery path that already exists before that flip happens. This
| entry authorizes it. Matching the rest of the app (no per-sensor ACL /
| ownership model exists — see `AuthApiController` token abilities: `*` for
| admin, `read` for everyone else), any authenticated user (auth:sanctum,
| enforced by `bootstrap/app.php`'s broadcasting middleware) may subscribe
| to any *existing* sensor's private channel. Do not invent a finer
| per-sensor ACL here.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('sensor.{sensorId}', function ($user, $sensorId) {
    return \App\Models\Sensor::query()->whereKey($sensorId)->exists();
});

// Stage 7: fixed-name private channel, no per-alert ACL — any authenticated user (the closure
// only runs once a request already passed the `/broadcasting/auth` route's auth:sanctum
// middleware, so `$user` is never null here) is authorized, matching every other alert-list
// endpoint (`GET /api/alerts`, `/api/alerts/active`) which is auth:sanctum-only with no
// per-record ownership check.
Broadcast::channel('alerts', function ($user) {
    return true;
});
