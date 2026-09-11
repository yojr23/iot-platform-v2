<?php

namespace App\Services\Ingestion;

use App\Models\DomainEventOutbox;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * PLAN.md Stage 4.1 / docs/implementation/adr-g1.md ADR-1.
 *
 * Existing code reused: mirrors `App\Services\Ingestion\RawSensorEventPublisher` field-by-field
 * (versioned envelope, same `Redis::command('xadd', ...)` call, same try/catch-false-on-failure
 * contract) — no second XADD transport style introduced.
 * Existing owner retired/delegated: n/a — no domain stream existed before this stage.
 * Compatibility window: none.
 */
class DomainEventPublisher
{
    public function publish(DomainEventOutbox $outbox): bool
    {
        Log::info('DomainEventPublisher:publish entry', [
            'outbox_id' => $outbox->id,
            'event_type' => $outbox->event_type,
        ]);

        $startTime = microtime(true);
        $streamName = (string) config('app.domain_events_stream', 'iot.domain-events');
        $occurredAt = $outbox->created_at?->toIso8601String() ?? now()->toIso8601String();

        $fields = [
            'event_id' => (string) $outbox->id,
            'event_type' => (string) $outbox->event_type,
            'event_version' => '1',
            'source' => (string) config('app.name', 'iot-platform-backend'),
            'aggregate_type' => (string) $outbox->aggregate_type,
            'aggregate_id' => (string) $outbox->aggregate_id,
            'occurred_at' => $occurredAt,
            'payload' => json_encode($outbox->payload ?? [], JSON_THROW_ON_ERROR),
        ];

        try {
            // phpredis signature: xAdd(key, id, array $fields) — fields is ONE associative
            // array, not flattened key/value positional args (that raised "xadd() expects at
            // most 6 arguments, N given" and broke every domain-event publish at runtime).
            Redis::command('xadd', [$streamName, '*', $fields]);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('DomainEventPublisher:publish completed', [
                'outbox_id' => $outbox->id,
                'event_type' => $outbox->event_type,
                'stream' => $streamName,
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            if ($durationMs > 100) {
                Log::warning('DomainEventPublisher:publish slow Redis XADD', ['duration_ms' => $durationMs, 'stream' => $streamName]);
            }

            return true;
        } catch (Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            Log::error('DomainEventPublisher:publish failed', [
                'outbox_id' => $outbox->id,
                'event_type' => $outbox->event_type,
                'stream' => $streamName,
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
                'duration_ms' => $durationMs,
            ]);

            return false;
        }
    }
}
