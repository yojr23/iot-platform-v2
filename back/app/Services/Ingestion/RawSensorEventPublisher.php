<?php

namespace App\Services\Ingestion;

use App\Models\RawSensorEvent;
use App\Services\Ingestion\Concerns\UsesRawRedisCommands;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RawSensorEventPublisher
{
    use UsesRawRedisCommands;

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
        $maxLength = (int) config('app.ingestion_raw_events_maxlen', 500000);
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
            // Raw XADD (unprefixed key) so this stream name matches EXACTLY what the raw-command
            // consumers (RawStreamConsumer via UsesRawRedisCommands) and Debezium read. The Redis
            // facade would prepend Laravel's key prefix (iot_platform_v2_back_database_), writing to
            // a different key than the consumers read from — a silent delivery break. XADD fields
            // are flattened key/value positional args in the RESP wire form.
            $this->rawXadd(Redis::connection('default'), $streamName, $maxLength, $fields);

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
