<?php

namespace App\Console\Commands;

use App\Services\Ingestion\Concerns\UsesRawRedisCommands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Debezium's Redis sink never trims its iot-cdc.* streams, and with maxmemory-policy noeviction they would
 * eventually fill Redis. Trims entries older than --hours (default 72) but never past what the consumer group
 * has not yet delivered or still holds pending (config/app.php forbids blind XTRIM: it could drop an
 * unprocessed CDC change). MySQL outbox rows stay the durable source.
 *
 * Also covers iot.raw-events and iot.domain-events: compose sets their XADD-time MAXLEN/MIN_AGE to 0 so a
 * consumer outage (e.g. raw-consumer down over a long weekend) can never trim unprocessed readings.
 */
class TrimCdcStreams extends Command
{
    use UsesRawRedisCommands;

    protected $signature = 'iot:cdc:trim {--hours=72}';

    protected $description = 'XTRIM CDC, raw and domain event streams up to the consumer-safe cutoff (older than --hours).';

    public function handle(): int
    {
        $conn = Redis::connection();
        $streams = [
            config('app.cdc_raw_outbox_stream') => config('app.cdc_outbox_consumer_group'),
            config('app.cdc_domain_outbox_stream') => config('app.cdc_outbox_consumer_group'),
            config('app.ingestion_raw_events_stream') => config('app.ingestion_raw_consumer_group'),
            config('app.domain_events_stream') => config('app.domain_events_consumer_group'),
        ];

        foreach ($streams as $stream => $group) {
            $cutoff = (int) (microtime(true) * 1000) - (int) $this->option('hours') * 3600000;

            try {
                $info = null;
                $groups = $this->raw($conn, ['XINFO', 'GROUPS', $stream]);
                if (! is_array($groups)) { // stream not created yet
                    $this->line("$stream: missing, skipped");
                    continue;
                }
                foreach ($groups as $g) {
                    $g = array_column(array_chunk($g, 2), 1, 0); // flat [k, v, k, v] => [k => v]
                    $info = $g['name'] === $group ? $g : $info;
                }
                if (! $info) {
                    $this->line("$stream: group $group missing, skipped");
                    continue;
                }
                $cutoff = min($cutoff, (int) explode('-', (string) $info['last-delivered-id'])[0]);
                $pending = $this->raw($conn, ['XPENDING', $stream, $group]);
                if (($pending[0] ?? 0) > 0) {
                    $cutoff = min($cutoff, (int) explode('-', (string) $pending[1])[0]);
                }
                $n = $this->raw($conn, ['XTRIM', $stream, 'MINID', max(0, $cutoff).'-0']);
                $this->line("$stream: trimmed $n");
            } catch (\Throwable $e) {
                $this->warn("$stream: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
