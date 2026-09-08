<?php

namespace App\Console\Commands;

use App\Services\Ingestion\RawReadingNormalizer;
use App\Services\Ingestion\RawStreamConsumer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * PLAN.md Stage 3.3 / docs/implementation/adr-g1.md — `raw-process-v1` consumer group over
 * `iot.raw-events`.
 *
 * Existing code reused: the existing `redis.default` connection (config/database.php) — no second
 * Redis client bootstrap layer. `RawStreamConsumer` owns all stream mechanics; this command is a
 * thin CLI wrapper (bounded batch/block options, signal handling for graceful shutdown).
 */
class ConsumeRawEvents extends Command
{
    protected $signature = 'raw:consume
        {--batch=100 : Max messages read/reclaimed per iteration}
        {--block=5000 : XREADGROUP BLOCK milliseconds}
        {--claim-idle=30000 : Minimum idle time (ms) before a pending message is reclaimed}
        {--consumer= : Consumer name (defaults to hostname-pid)}
        {--once : Run a single iteration and exit}
        {--max-iterations=0 : Stop after N iterations (0 = unbounded)}';

    protected $description = 'Consume iot.raw-events via consumer group raw-process-v1 (Stage 3 durable raw ingestion).';

    public function handle(RawReadingNormalizer $normalizer): int
    {
        $consumerName = $this->option('consumer') ?: sprintf('%s-%d', gethostname() ?: 'worker', getmypid());
        $batch = max(1, (int) $this->option('batch'));
        $block = max(0, (int) $this->option('block'));
        $claimIdle = max(0, (int) $this->option('claim-idle'));
        $once = (bool) $this->option('once');
        $maxIterations = (int) $this->option('max-iterations');

        $consumer = new RawStreamConsumer(
            Redis::connection('default'),
            $normalizer,
            (string) config('app.ingestion_raw_events_stream'),
            (string) config('app.ingestion_raw_consumer_group'),
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

        $this->info("raw:consume started as [{$consumerName}]");

        $iterations = 0;

        do {
            $stats = $consumer->runOnce($consumerName, $batch, $block, $claimIdle);

            $this->info(sprintf(
                'raw:consume iteration acked=%d dlq=%d pending=%d',
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
