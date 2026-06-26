<?php

namespace App\Http\Requests\Api;

use App\Models\Sensor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'sensor_type_id' => ['required', 'exists:sensor_types,id'],
            'device_id' => ['nullable', 'exists:devices,id'],
            'sensor_id' => ['nullable', 'exists:sensors,id'],
            'min_value' => ['nullable', 'numeric'],
            'max_value' => ['nullable', 'numeric'],
            'severity' => ['required', 'in:info,warning,danger'],
            'message' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateThresholds($validator);
            $this->validateSensorScope($validator);
        });
    }

    private function validateThresholds(Validator $validator): void
    {
        $hasMin = $this->filled('min_value');
        $hasMax = $this->filled('max_value');

        if (! $hasMin && ! $hasMax) {
            $validator->errors()->add('min_value', 'Debes definir un valor minimo o maximo para la regla.');
            return;
        }

        if ($hasMin && $hasMax && (float) $this->input('max_value') <= (float) $this->input('min_value')) {
            $validator->errors()->add('max_value', 'El valor maximo debe ser mayor al minimo cuando ambos se definen.');
        }
    }

    private function validateSensorScope(Validator $validator): void
    {
        if (! $this->filled('sensor_id')) {
            return;
        }

        $sensor = Sensor::find($this->input('sensor_id'));
        if (! $sensor) {
            return;
        }

        if ($this->filled('device_id') && $sensor->device_id !== (int) $this->input('device_id')) {
            $validator->errors()->add('sensor_id', 'El sensor seleccionado no pertenece al dispositivo especificado.');
        }

        if ($sensor->sensor_type_id !== (int) $this->input('sensor_type_id')) {
            $validator->errors()->add('sensor_type_id', 'El tipo de sensor no coincide con el sensor seleccionado.');
        }
    }
}
