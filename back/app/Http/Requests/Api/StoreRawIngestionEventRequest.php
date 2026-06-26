<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreRawIngestionEventRequest extends FormRequest
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
            'topic' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'received_at' => ['nullable', 'date'],
            'payload' => ['required', 'array'],
            'payload.device' => ['nullable', 'array'],
            'payload.device.node_id' => ['nullable', 'string', 'max:255'],
            'payload.sensors' => ['required', 'array'],
            'payload.qc' => ['nullable', 'array'],
            'payload.qc.valid' => ['nullable', 'boolean'],
        ];
    }
}
