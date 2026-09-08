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
            // Producer-generated identity (PLAN.md Stage 2.4 / adr-g1.md): the upstream producer
            // creates this once and must resend the same value on MQTT/HTTP retries so downstream
            // consumers can dedupe. Optional for now (mixed-version producers), backstopped by the
            // unique (source, source_event_id) index added in Stage 2.5.
            'source_event_id' => ['nullable', 'string', 'max:255'],
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
