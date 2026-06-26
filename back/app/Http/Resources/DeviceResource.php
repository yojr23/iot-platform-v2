<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'device_type_id' => $this->device_type_id,
            'lab_id' => $this->lab_id,
            'status' => (bool) $this->status,
            'is_active' => (bool) $this->is_active,
            'ip_address' => $this->ip_address,
            'mac_address' => $this->mac_address,
            'last_communication' => $this->last_communication?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'device_type' => $this->whenLoaded('deviceType'),
            'lab' => $this->whenLoaded('lab'),
            'sensors' => SensorResource::collection($this->whenLoaded('sensors')),
            'status_logs' => $this->whenLoaded('statusLogs'),
        ];
    }
}
