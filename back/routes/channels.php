<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Stage 2 public-vs-private decision (PLAN.md 2.3, audit.md open Q#3):
| `sensor.{id}`, `alerts`, and `device-status` (App\Events\NewSensorReading,
| NewAlertTriggered, DeviceStatusUpdated) are plain `Illuminate\Broadcasting\
| Channel` instances, i.e. PUBLIC channels. They are intentionally public:
| they back the guest dashboard, carry no device keys/tokens and no private
| user fields (only sensor/device/lab names, reading values, alert
| messages/severity). Public channels never hit this file or the
| `/broadcasting/auth` endpoint, so no entry is required or added for them.
| Do not convert them to PrivateChannel without a real non-public field.
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
