<?php

namespace App\Jobs;

use App\Services\Ingestion\DomainOutboxRelay;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * PLAN.md Stage 4.1 / docs/implementation/adr-g1.md ADR-1 — domain-outbox counterpart of
 * `App\Jobs\RelayRawOutboxJob`. `DB::afterCommit()` low-latency wake-up hint only; the durable
 * `domain:relay-outbox` discovery loop is the actual delivery guarantee (see DomainOutboxRelay).
 *
 * Existing code reused: `DomainOutboxRelay` — no relay logic lives in this job.
 */
class RelayDomainOutboxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(DomainOutboxRelay $relay): void
    {
        $startTime = microtime(true);
        Log::info('RelayDomainOutboxJob: processing');

        $relay->relayPending();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('RelayDomainOutboxJob: completed', ['duration_ms' => $durationMs]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RelayDomainOutboxJob: failed', [
            'exception' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}
