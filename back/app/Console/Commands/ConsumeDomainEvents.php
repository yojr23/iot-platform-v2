<?php

namespace App\Console\Commands;

use App\Services\Ingestion\DomainEventBroadcastConsumer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * PLAN.md Stage 4.1/4.4 — `browser-delivery-v1` consumer group over `iot.domain-events`.
 *
 * Existing code reused: structurally identical to `raw:consume`
 * (`App\Console\Commands\ConsumeRawEvents`) — the existing `redis.default` connection, same
 * signature shape, same signal handling for graceful shutdown. `DomainEventBroadcastConsumer` owns
 * all stream mechanics; this command is a thin CLI wrapper.
 */
class ConsumeDomainEvents extends Command
{
    protected $signature = 'domain:consume
        {--batch=100 : Max messages read/reclaimed per iteration}
        {--block=5000 : XREADGROUP BLOCK milliseconds}
        {--claim-idle=30000 : Minimum idle time (ms) before a pending message is reclaimed}
        {--consumer= : Consumer name (defaults to hostname-pid)}
        {--once : Run a single iteration and exit}
        {--max-iterations=0 : Stop after N iterations (0 = unbounded)}';

    protected $description = 'Consume iot.domain-events via consumer group browser-delivery-v1 (Stage 4 direct broadcast fan-out).';

    public function handle(): int
    {
        $consumerName = $this->option('consumer') ?: sprintf('%s-%d', gethostname() ?: 'worker', getmypid());
        $batch = max(1, (int) $this->option('batch'));
        $block = max(0, (int) $this->option('block'));
        $claimIdle = max(0, (int) $this->option('claim-idle'));
        $once = (bool) $this->option('once');
        $maxIterations = (int) $this->option('max-iterations');

        $consumer = new DomainEventBroadcastConsumer(
            Redis::connection('default'),
            (string) config('app.domain_events_stream'),
            (string) config('app.domain_events_consumer_group'),
            (string) config('app.ingestion_dead_letter_stream'),
        );

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

        $this->info("domain:consume started as [{$consumerName}]");

        $iterations = 0;

        do {
            $stats = $consumer->runOnce($consumerName, $batch, $block, $claimIdle);

            $this->info(sprintf(
                'domain:consume iteration acked=%d dlq=%d pending=%d',
                $stats['acked'],
                $stats['dlq'],
                $stats['pending'],
            ));

            $iterations++;

            if ($once) {
                break;
            }

            if ($maxIterations > 0 && $iterations >= $maxIterations) {
                break;
            }
        } while (! $shouldStop);

        return self::SUCCESS;
    }
}
