<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertResource extends JsonResource
{
    /**
     * @return array<string,mixed>
     */
    public function toArray(Request $request): array
    {
        $reading = $this->sensorReading;
        $sensor = $reading?->sensor;
        $device = $sensor?->device;
        $rule = $this->alertRule;

        return [
            'id' => $this->id,
            'resolved' => (bool) $this->resolved,
            'resolved_at' => $this->timestamp($this->resolved_at),
            'created_at' => $this->timestamp($this->created_at),
            'updated_at' => $this->timestamp($this->updated_at),
            'sensor_reading' => $reading ? [
                'id' => $reading->id,
                'value' => (float) $reading->value,
                'reading_time' => $reading->reading_time?->toIso8601String(),
            ] : null,
            'alert_rule' => $rule ? [
                'id' => $rule->id,
                'name' => $rule->name,
                'severity' => $rule->severity,
                'message' => $rule->message,
                'min_value' => $rule->min_value !== null ? (float) $rule->min_value : null,
                'max_value' => $rule->max_value !== null ? (float) $rule->max_value : null,
            ] : null,
            'sensor' => $sensor ? [
                'id' => $sensor->id,
                'name' => $sensor->name,
                'sensor_type' => $sensor->sensorType,
            ] : null,
            'device' => $device ? [
                'id' => $device->id,
                'name' => $device->name,
                'lab' => $device->lab,
            ] : null,
        ];
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return $value !== null ? (string) $value : null;
    }
}
