<?php

namespace App\Console\Commands;

use App\Services\Ingestion\DeadLetterStreamService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;

class ReplayDeadLetter extends Command
{
    protected $signature = 'dlq:replay
        {id : DLQ stream entry ID}
        {--source= : Explicit approved source stream containing the original entry}
        {--yes : Confirm that a duplicate delivery is acceptable}';
    protected $description = 'Atomically re-drive one DLQ entry and append an audit record.';

    public function handle(): int
    {
        if (! $this->option('yes')) {
            $this->error('Refusing to replay without --yes; re-drive can deliver the event again.');
            return self::INVALID;
        }

        $source = (string) $this->option('source');
        $allowed = array_values(array_filter([
            config('app.ingestion_raw_events_stream', 'iot.raw-events'),
            config('app.domain_events_stream', 'iot.domain-events'),
            config('app.cdc_raw_outbox_stream', ''),
            config('app.cdc_domain_outbox_stream', ''),
        ]));

        try {
            $result = (new DeadLetterStreamService(
                Redis::connection('default'),
                (string) config('app.ingestion_dead_letter_stream', 'iot.dead-letter-events'),
                $allowed,
                (string) config('app.ingestion_dead_letter_replay_audit_stream', 'iot.dead-letter-replays'),
            ))->replay((string) $this->argument('id'), $source, (string) (gethostname() ?: 'operator'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::INVALID;
        }

        $this->info(sprintf('%s: replay_id=%s', $result['status'], $result['replay_id'] ?? 'n/a'));
        return self::SUCCESS;
    }
}
