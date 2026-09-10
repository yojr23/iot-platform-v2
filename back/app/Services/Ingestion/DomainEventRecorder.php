<?php

namespace App\Services\Ingestion;

use App\Jobs\RelayDomainOutboxJob;
use App\Models\DomainEventOutbox;
use Illuminate\Support\Facades\Log;

/**
 * PLAN.md Stage 4.1/4.2 — the one place a transition owner writes a durable domain fact.
 *
 * Existing code reused: mirrors exactly what `IngestionController::store()` already does for the
 * raw pipeline (write the outbox row inside the caller's DB transaction, then dispatch the relay
 * job with `afterCommit()` as a low-latency wake-up hint only — the durable delivery guarantee is
 * `domain:relay-outbox`'s discovery loop, per ADR-1). Extracted into one small class instead of
 * duplicating those two calls in both `AlertLifecycleService` and `DeviceService` (RC3: "non-atomic
 * persist+publish" is closed by always writing the outbox row in the same transaction as the
 * aggregate mutation that calls this).
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

        try {
            RelayDomainOutboxJob::dispatch()->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('DomainEventRecorder: failed to dispatch relay job', [
                'outbox_id' => $outbox->id,
                'event_type' => $eventType,
                'exception' => $e->getMessage(),
            ]);
        }

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
