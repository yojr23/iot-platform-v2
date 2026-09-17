<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SensorResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'device_id' => $this->device_id,
            'sensor_type_id' => $this->sensor_type_id,
            'status' => (bool) $this->status,
            'public_monitoring_enabled' => (bool) $this->public_monitoring_enabled,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'unit' => $this->sensorType?->unit,
            'sensor_type' => $this->whenLoaded('sensorType', fn () => [
                'id' => $this->sensorType->id,
                'name' => $this->sensorType->name,
                'unit' => $this->sensorType->unit,
            ]),
            'device' => new DeviceResource($this->whenLoaded('device')),
            // SEC-RT-002 / PENDING MAC VERIFICATION: these two fields carry reading TELEMETRY (raw
            // values), the same data class REST reading endpoints and the private `sensor.{id}`
            // channel gate behind `sensor_reading.view` (see SensorPolicy::viewReading,
            // ResourceAccessService::canViewSensorReadings). Every caller of this resource —
            // show()/index() (sensor.view only) and DeviceApiController::sensors()
            // (device.view only) — previously embedded these unconditionally whenever the relation
            // happened to be eager-loaded, leaking telemetry to a metadata-only permission. Fixed
            // once here (the shared resource all those controllers route through) instead of
            // duplicating a check per caller.
            'latest_readings' => $this->when($this->includeTelemetry($request), fn () => $this->whenLoaded('readings')),
            // D4 (front_rebuild_plan/MAIN_PARITY_GAPS_PLAN.md): backed by Sensor::latestReading(),
            // a hasOne-"of many" relation added alongside the existing `readings` hasMany (see
            // Sensor.php) so each sensor's true latest row is resolved without the N+1/limit(1)
            // grouping bug a naive `readings` eager-load-with-limit would have. Callers that
            // eager-load `latestReading` (e.g. DeviceApiController::sensors()) get this populated;
            // otherwise the key is simply absent from the response.
            'latest_reading' => $this->when($this->includeTelemetry($request), fn () => $this->whenLoaded(
                'latestReading',
                fn () => optional($this->latestReading)->only(['value', 'reading_time'])
            )),
        ];
    }

    /**
     * PENDING MAC VERIFICATION: telemetry-field gate for this resource (SEC-RT-002 parity).
     *
     * A request with no authenticated Sanctum user is the IoT device-key path (`iotIndex()`,
     * `X-Device-Key` auth, no `auth:sanctum` middleware) — a different, non-RBAC auth boundary
     * where a device legitimately reads back its own sensors' latest values. That path is left
     * unchanged (`$user === null` short-circuits to true) so this fix does not touch device
     * ingestion behavior; it only closes the gap for authenticated Sanctum users who lack
     * `sensor_reading.view`.
     */
    private function includeTelemetry(Request $request): bool
    {
        $user = $request->user();

        return $user === null || $user->can('sensor_reading.view');
    }
}
