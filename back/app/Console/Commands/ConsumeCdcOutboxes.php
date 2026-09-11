<?php

namespace App\Console\Commands;

use App\Services\Ingestion\Cdc\CdcOutboxStreamConsumer;
use App\Services\Ingestion\DomainEventPublisher;
use App\Services\Ingestion\RawSensorEventPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Gate 10 (PLAN.md Stage 10) — the single blocking CDC consumer that replaces both retired periodic
 * outbox relays.
 *
 * The loop is a repeated blocking Redis read (XREADGROUP BLOCK) + XAUTOCLAIM lease recovery. There
 * is NO sleep/usleep, NO interval option, and NO pending-discovery DB query — the durable trigger
 * is the MySQL binlog captured by Debezium onto the CDC streams. This is intentionally NOT polling.
 */
class ConsumeCdcOutboxes extends Command
{
    protected $signature = 'cdc:consume-outboxes
        {--batch=100 : Max messages read/reclaimed per iteration}
        {--block=5000 : XREADGROUP BLOCK milliseconds}
        {--claim-idle=30000 : Minimum idle time (ms) before a pending message is reclaimed}
        {--consumer= : Consumer name (defaults to hostname-pid)}
        {--once : Run a single iteration and exit}
        {--max-iterations=0 : Stop after N iterations (0 = unbounded)}';

    protected $description = 'Publish committed outboxes from the Debezium CDC Redis streams (Gate 10 no-polling).';

    public function handle(RawSensorEventPublisher $rawPublisher, DomainEventPublisher $domainPublisher): int
    {
        $consumerName = $this->option('consumer') ?: sprintf('%s-%d', gethostname() ?: 'worker', getmypid());
        $batch = max(1, (int) $this->option('batch'));
        $block = max(0, (int) $this->option('block'));
        $claimIdle = max(0, (int) $this->option('claim-idle'));
        $once = (bool) $this->option('once');
        $maxIterations = (int) $this->option('max-iterations');

        $consumer = new CdcOutboxStreamConsumer(
            Redis::connection('default'),
            $rawPublisher,
            $domainPublisher,
            (string) config('app.cdc_raw_outbox_stream'),
            (string) config('app.cdc_domain_outbox_stream'),
            (string) config('app.cdc_outbox_consumer_group'),
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

        $this->info("cdc:consume-outboxes started as [{$consumerName}]");

        $iterations = 0;

        do {
            $stats = $consumer->runOnce($consumerName, $batch, $block, $claimIdle);

            $this->info(sprintf(
                'cdc:consume-outboxes iteration acked=%d dlq=%d pending=%d',
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
