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
        $streamName = (string) config('app.domain_events_stream', 'iot.domain-events');
        $occurredAt = $outbox->created_at?->toIso8601String() ?? now()->toIso8601String();

        // Versioned durable contract (PLAN.md golden rule "no unversioned durable events"), same
        // envelope shape as RawSensorEventPublisher. `event_id` is the outbox row's own DB id
        // (mirrors the raw pipeline using `raw_sensor_events.id` as the dedup key) — the consumer
        // resolves the business row by it and dedupes via `delivered_at` on that same row.
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

        $args = [$streamName, '*'];

        foreach ($fields as $key => $value) {
            $args[] = $key;
            $args[] = $value;
        }

        try {
            Redis::command('xadd', $args);

            return true;
        } catch (Throwable $e) {
            Log::error('Domain event publish failed', [
                'outbox_id' => $outbox->id,
                'event_type' => $outbox->event_type,
                'stream' => $streamName,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
