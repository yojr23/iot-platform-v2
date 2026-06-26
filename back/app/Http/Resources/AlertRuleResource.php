<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertRuleResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'message' => $this->message,
            'severity' => $this->severity,
            'min_value' => $this->min_value !== null ? (float) $this->min_value : null,
            'max_value' => $this->max_value !== null ? (float) $this->max_value : null,
            'sensor_type' => $this->whenLoaded('sensorType'),
            'device' => $this->whenLoaded('device'),
            'sensor' => $this->whenLoaded('sensor'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
