<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal authenticated recovery contract. It deliberately excludes device metadata so the
 * realtime projection has one authoritative owner for status facts and their watermarks.
 */
class DeviceStatusSnapshotResource extends JsonResource
{
    /**
     * @return array<string, bool|int|string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'device_id' => $this->id,
            'status' => (bool) $this->status,
            'is_active' => (bool) $this->is_active,
            'changed_at' => $this->updated_at?->toIso8601String(),
            'event_sequence' => (int) ($this->event_sequence ?? 0),
        ];
    }
}
