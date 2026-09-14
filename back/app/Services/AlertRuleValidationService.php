<?php

namespace App\Services;

use App\Models\AlertRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AlertRuleValidationService
{
    /**
     * Validate alert rule consistency before creation or update.
     */
    public function validate(AlertRule $rule): void
    {
        $errors = [];

        // Validate that device_id and sensor_id are consistent
        if ($rule->sensor_id && $rule->device_id) {
            $sensor = \App\Models\Sensor::find($rule->sensor_id);
            if ($sensor && $sensor->device_id !== $rule->device_id) {
                $errors['device_id'] = 'El sensor no pertenece al dispositivo seleccionado.';
            }
        }

        // Validate threshold values
        if ($rule->min_value !== null && $rule->max_value !== null) {
            if ($rule->min_value > $rule->max_value) {
                $errors['min_value'] = 'El valor mínimo no puede ser mayor que el valor máximo.';
            }
        }

        // Validate severity
        $validSeverities = ['low', 'medium', 'high', 'critical'];
        if (! in_array($rule->severity, $validSeverities, true)) {
            $errors['severity'] = 'La severidad debe ser: low, medium, high o critical.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Validate alert rule data for creation.
     *
     * @param  array<string, mixed>  $data
     */
    public function validateCreationData(array $data): array
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'condition' => ['required', 'string', 'in:above,below,outside_range,inside_range,equal'],
            'min_value' => ['nullable', 'numeric'],
            'max_value' => ['nullable', 'numeric'],
            'severity' => ['required', 'string', 'in:low,medium,high,critical'],
            'sensor_id' => ['required', 'exists:sensors,id'],
            'device_id' => ['nullable', 'exists:devices,id'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        // Validate consistency
        if (isset($validated['sensor_id']) && isset($validated['device_id']) && $validated['device_id']) {
            $sensor = \App\Models\Sensor::find($validated['sensor_id']);
            if ($sensor && $sensor->device_id !== $validated['device_id']) {
                throw ValidationException::withMessages([
                    'device_id' => 'El sensor no pertenece al dispositivo seleccionado.',
                ]);
            }
        }

        return $validated;
    }

    /**
     * Validate alert rule data for update.
     *
     * @param  array<string, mixed>  $data
     */
    public function validateUpdateData(array $data, AlertRule $rule): array
    {
        $validator = Validator::make($data, [
            'name' => ['sometimes', 'string', 'max:255'],
            'condition' => ['sometimes', 'string', 'in:above,below,outside_range,inside_range,equal'],
            'min_value' => ['nullable', 'numeric'],
            'max_value' => ['nullable', 'numeric'],
            'severity' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'sensor_id' => ['sometimes', 'exists:sensors,id'],
            'device_id' => ['nullable', 'exists:devices,id'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        // Validate consistency with merged data
        $sensorId = $validated['sensor_id'] ?? $rule->sensor_id;
        $deviceId = $validated['device_id'] ?? $rule->device_id;

        if ($sensorId && $deviceId) {
            $sensor = \App\Models\Sensor::find($sensorId);
            if ($sensor && $sensor->device_id !== $deviceId) {
                throw ValidationException::withMessages([
                    'device_id' => 'El sensor no pertenece al dispositivo seleccionado.',
                ]);
            }
        }

        return $validated;
    }
}
