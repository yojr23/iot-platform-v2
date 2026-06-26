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
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'unit' => $this->sensorType?->unit,
            'sensor_type' => $this->whenLoaded('sensorType'),
            'device' => new DeviceResource($this->whenLoaded('device')),
            'latest_readings' => $this->whenLoaded('readings'),
        ];
    }
}
