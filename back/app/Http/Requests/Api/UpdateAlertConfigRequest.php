<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlertConfigRequest extends FormRequest
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
            'mail_enabled' => ['required', 'boolean'],
            'alert_sound_enabled' => ['required', 'boolean'],
            'alert_threshold' => ['required', 'numeric', 'min:0'],
            'sensor_update_interval' => ['required', 'numeric', 'min:1000'],
            'danger_email_rate_limit_seconds' => ['required', 'integer', 'min:0'],
        ];
    }
}
