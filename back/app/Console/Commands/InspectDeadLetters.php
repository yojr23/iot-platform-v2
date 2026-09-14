<?php

namespace App\Console\Commands;

use App\Services\Ingestion\DeadLetterStreamService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class InspectDeadLetters extends Command
{
    protected $signature = 'dlq:inspect {--limit=50 : Maximum entries to display} {--json : Render JSON instead of a table}';
    protected $description = 'Inspect entries in the durable Redis dead-letter stream.';

    public function handle(): int
    {
        $service = new DeadLetterStreamService(
            Redis::connection('default'),
            (string) config('app.ingestion_dead_letter_stream', 'iot.dead-letter-events'),
        );
        $entries = $service->inspect((int) $this->option('limit'));

        if ($this->option('json')) {
            $this->line((string) json_encode($entries, JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->table(['id', 'fields'], array_map(
            fn (array $entry): array => [$entry['id'], json_encode($entry['fields'], JSON_UNESCAPED_SLASHES)],
            $entries,
        ));

        return self::SUCCESS;
    }
}
