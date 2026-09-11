<?php

namespace App\Services\Ingestion;

use App\Models\DomainEventOutbox;
use Illuminate\Support\Facades\Log;

/**
 * PLAN.md Stage 4.1/4.2 — the one place a transition owner writes a durable domain fact.
 *
 * Existing code reused: mirrors exactly what `IngestionController::store()` does for the raw
 * pipeline (write the outbox row inside the caller's DB transaction, nothing else). Extracted into
 * one small class instead of duplicating that call in both `AlertLifecycleService` and
 * `DeviceService` (RC3: "non-atomic persist+publish" is closed by always writing the outbox row in
 * the same transaction as the aggregate mutation that calls this).
 * Gate 10: this recorder dispatches NO relay job. The committed DomainEventOutbox row is captured
 * from the MySQL binlog by Debezium and drained by `cdc:consume-outboxes`.
 * Existing owner retired/delegated: n/a.
 * Compatibility window: none.
 */
class DomainEventRecorder
{
    /**
     * @param  array<string,mixed>  $payload
     */
    public function record(string $eventType, string $aggregateType, int|string $aggregateId, array $payload): DomainEventOutbox
    {
        Log::info('DomainEventRecorder:record entry', [
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
        ]);

        $startTime = microtime(true);

        $outbox = DomainEventOutbox::create([
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => (string) $aggregateId,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('DomainEventRecorder:record completed', [
            'outbox_id' => $outbox->id,
            'event_type' => $eventType,
            'duration_ms' => $durationMs,
        ]);

        if ($durationMs > 100) {
            Log::warning('DomainEventRecorder:record slow operation', ['duration_ms' => $durationMs, 'table' => 'domain_event_outbox']);
        }

        return $outbox;
    }
}
