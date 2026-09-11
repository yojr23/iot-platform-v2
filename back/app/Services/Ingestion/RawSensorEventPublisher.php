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
        Log::info('RawSensorEventPublisher:publish entry', [
            'event_id' => $event->id,
            'node_id' => $event->node_id,
        ]);

        $startTime = microtime(true);
        $streamName = (string) config('app.ingestion_raw_events_stream', 'iot.raw-events');
        $receivedAt = $event->received_at?->toIso8601String();

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

        try {
            // phpredis signature: xAdd(key, id, array $fields) — fields is ONE associative
            // array, not flattened key/value positional args.
            Redis::command('xadd', [$streamName, '*', $fields]);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('RawSensorEventPublisher:publish completed', [
                'event_id' => $event->id,
                'stream' => $streamName,
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            if ($durationMs > 100) {
                Log::warning('RawSensorEventPublisher:publish slow Redis XADD', ['duration_ms' => $durationMs, 'stream' => $streamName]);
            }

            return true;
        } catch (Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            Log::error('RawSensorEventPublisher:publish failed', [
                'event_id' => $event->id,
                'stream' => $streamName,
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
                'duration_ms' => $durationMs,
            ]);

            return false;
        }
    }
}
