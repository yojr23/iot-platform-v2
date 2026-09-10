<?php

namespace App\Jobs;

use App\Services\Ingestion\RawOutboxRelay;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * PLAN.md Stage 3.2 / docs/implementation/adr-g1.md ADR-1: `DB::afterCommit()` low-latency
 * wake-up hint for the transactional outbox relay. This is intentionally NOT the sole delivery
 * mechanism — if this job is lost (process dies between commit and dispatch, queue driver
 * outage, etc.) the outbox row is still recovered by the durable `ingestion:relay-outbox`
 * discovery loop's lease-based claim. $tries=1 is correct here: a failed/lost run of this hint is
 * not a lost delivery, the discovery loop will pick the row up on its next sweep.
 *
 * Existing code reused: `RawOutboxRelay` (this stage) — no relay logic lives in this job.
 */
class RelayRawOutboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(RawOutboxRelay $relay): void
    {
        $startTime = microtime(true);
        Log::info('RelayRawOutboxJob: processing');

        $relay->relayPending();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('RelayRawOutboxJob: completed', ['duration_ms' => $durationMs]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RelayRawOutboxJob: failed', [
            'exception' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}
