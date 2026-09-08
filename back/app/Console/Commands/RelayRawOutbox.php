<?php

namespace App\Console\Commands;

use App\Services\Ingestion\RawOutboxRelay;
use Illuminate\Console\Command;

/**
 * PLAN.md Stage 3.2 / docs/implementation/adr-g1.md ADR-1 anti-stranding mechanism.
 *
 * Durable committed-outbox discovery loop: run continuously under a supervisor as the relay
 * worker process, or with `--once` from `schedule()`/cron. Recovers rows whose `afterCommit()`
 * wake-up hint (`RelayRawOutboxJob`) was lost to a process crash or queue outage, and rows whose
 * previous claim lease expired without completing (crashed relay worker).
 *
 * Existing code reused: `RawOutboxRelay` — this command is a thin CLI wrapper, no relay logic
 * lives here.
 */
class RelayRawOutbox extends Command
{
    protected $signature = 'ingestion:relay-outbox
        {--limit=100 : Max outbox rows claimed per sweep}
        {--interval=5 : Seconds to sleep between sweeps in loop mode}
        {--once : Run a single sweep and exit (for cron/scheduler use)}
        {--max-iterations=0 : Stop after N sweeps (0 = unbounded, loop mode only)}';

    protected $description = 'Durable discovery/recovery sweep for the raw-event transactional outbox (Stage 3).';

    public function handle(RawOutboxRelay $relay): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $interval = max(0, (int) $this->option('interval'));
        $once = (bool) $this->option('once');
        $maxIterations = (int) $this->option('max-iterations');

        $shouldStop = false;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () use (&$shouldStop): void {
                $shouldStop = true;
            });
            pcntl_signal(SIGINT, function () use (&$shouldStop): void {
                $shouldStop = true;
            });
        }

        $iterations = 0;

        do {
            $stats = $relay->relayPending($limit);

            $this->info(sprintf(
                'ingestion:relay-outbox sweep claimed=%d published=%d failed=%d',
                $stats['claimed'],
                $stats['published'],
                $stats['failed'],
            ));

            $iterations++;

            if ($once) {
                break;
            }

            if ($maxIterations > 0 && $iterations >= $maxIterations) {
                break;
            }

            if ($interval > 0) {
                sleep($interval);
            }
        } while (! $shouldStop);

        return self::SUCCESS;
    }
}
