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

        // Versioned durable contract for iot.raw-events (PLAN.md Golden Rule "no unversioned durable
        // events"; matches the G1 spike envelope). event_id stays the DB receipt id — the consumer
        // resolves the business row by it — while source_event_id carries the producer's stable
        // dedup key. Additive fields only; a consumer written against the old flat shape still reads
        // event_id/node_id/topic/received_at/status unchanged.
        $fields = [
            'event_id' => (string) $event->id,
            'event_type' => 'raw.sensor.received',
            'event_version' => '1',
            'source' => (string) ($event->source ?? 'ingestion_service'),
            'source_event_id' => (string) ($event->source_event_id ?? ''),
            'occurred_at' => (string) ($receivedAt ?? now()->toIso8601String()),
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
