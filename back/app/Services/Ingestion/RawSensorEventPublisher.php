<?php

namespace App\Services\Ingestion;

use App\Models\RawSensorEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RawSensorEventPublisher
{
    /**
     * Publica un evento mínimo para consumo asíncrono futuro.
     * No debe romper el flujo de ingesta si Redis no está disponible.
     */
    public function publish(RawSensorEvent $event): bool
    {
        $streamName = (string) config('app.ingestion_raw_events_stream', 'iot.raw-events');
        $receivedAt = $event->received_at?->toIso8601String();

        $fields = [
            'event_id' => (string) $event->id,
            'node_id' => (string) ($event->node_id ?? ''),
            'topic' => (string) ($event->topic ?? ''),
            'received_at' => (string) ($receivedAt ?? ''),
            'status' => (string) $event->status,
        ];

        $args = [$streamName, '*'];

        foreach ($fields as $key => $value) {
            $args[] = $key;
            $args[] = $value;
        }

        try {
            Redis::command('xadd', $args);
            return true;
        } catch (Throwable $e) {
            Log::error('Raw sensor event publish failed', [
                'event_id' => $event->id,
                'stream' => $streamName,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
