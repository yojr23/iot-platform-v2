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
            // D6 (front_rebuild_plan/MAIN_PARITY_GAPS_PLAN.md): the api_key column already exists
            // and is auto-generated in Device::boot(); it was simply never serialized. Reusing the
            // existing attribute and column — no new abstraction — gated to admin requesters only
            // via `when()` so it is entirely absent (not null) from non-admin/guest responses.
            'api_key' => $this->when((bool) optional($request->user())->is_admin, $this->api_key),
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
