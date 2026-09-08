<?php

namespace App\Services\Ingestion;

use App\Jobs\RelayDomainOutboxJob;
use App\Models\DomainEventOutbox;

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
        $outbox = DomainEventOutbox::create([
            'event_type' => $eventType,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => (string) $aggregateId,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        // Caller is expected to be inside a DB transaction (resolve/resolveAll/changeStatus all
        // wrap this call); afterCommit() only fires once that transaction actually commits, so a
        // rolled-back mutation never leaves a stray outbox row.
        RelayDomainOutboxJob::dispatch()->afterCommit();

        return $outbox;
    }
}
