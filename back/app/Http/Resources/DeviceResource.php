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
        // SEC-BOLA-002: ip_address/mac_address/serial_number are internal network
        // topology, not needed by the standard authenticated UI — admin-only.
        $isAdmin = (bool) ($request->user()?->is_admin);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'serial_number' => $this->when($isAdmin, $this->serial_number),
            // D6: device ingestion credential is hash-only. Plaintext is never stored;
            // use POST /devices/{device}/rotate-key to obtain a new key (shown once).
            'api_key_prefix' => $this->when($isAdmin && $request->route('device') !== null, $this->api_key_prefix),
            'api_key_last_rotated_at' => $this->when($isAdmin && $request->route('device') !== null, $this->api_key_last_rotated_at?->toIso8601String()),
            'device_type_id' => $this->device_type_id,
            'lab_id' => $this->lab_id,
            'status' => (bool) $this->status,
            'is_active' => (bool) $this->is_active,
            'ip_address' => $this->when($isAdmin, $this->ip_address),
            'mac_address' => $this->when($isAdmin, $this->mac_address),
            'last_communication' => $this->last_communication?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'device_type' => $this->whenLoaded('deviceType', fn () => [
                'id' => $this->deviceType->id,
                'name' => $this->deviceType->name,
            ]),
            'lab' => $this->whenLoaded('lab', fn () => [
                'id' => $this->lab->id,
                'name' => $this->lab->name,
            ]),
            'sensors' => SensorResource::collection($this->whenLoaded('sensors')),
            'status_logs' => $this->whenLoaded('statusLogs'),
        ];
    }
}
