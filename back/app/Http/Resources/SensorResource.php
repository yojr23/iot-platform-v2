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
            'sensor_type' => $this->whenLoaded('sensorType'),
            'device' => new DeviceResource($this->whenLoaded('device')),
            'latest_readings' => $this->whenLoaded('readings'),
            // D4 (front_rebuild_plan/MAIN_PARITY_GAPS_PLAN.md): backed by Sensor::latestReading(),
            // a hasOne-"of many" relation added alongside the existing `readings` hasMany (see
            // Sensor.php) so each sensor's true latest row is resolved without the N+1/limit(1)
            // grouping bug a naive `readings` eager-load-with-limit would have. Callers that
            // eager-load `latestReading` (e.g. DeviceApiController::sensors()) get this populated;
            // otherwise the key is simply absent from the response.
            'latest_reading' => $this->whenLoaded(
                'latestReading',
                fn () => optional($this->latestReading)->only(['value', 'reading_time'])
            ),
        ];
    }
}
