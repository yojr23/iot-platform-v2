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
            // D6: api_key is the device ingestion credential. Admin-only AND single-device-detail
            // only — routes that bind a {device} param (show/update). Bulk list routes (/devices
            // index) never carry the param, so the collection response omits the key entirely,
            // keeping every device's credential out of the list-page network payload (smaller
            // secret blast radius). The reveal/copy control lives on the detail page.
            'api_key' => $this->when(
                (bool) optional($request->user())->is_admin && $request->route('device') !== null,
                $this->api_key
            ),
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
