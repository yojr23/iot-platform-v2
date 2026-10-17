<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Storage tiering. Measurements (sensor_readings) and archive tables are never deleted.
 * ponytail: age is by created_at/processed_at without an index (daily full scan of ids);
 * add an index on raw_sensor_events(status, created_at) if the hot table grows past millions.
 */
class TierStorage extends Command
{
    protected $signature = 'iot:storage:tier {--dry-run : Only print how many rows would move or be deleted}';

    protected $description = 'Move old processed raw events and audit logs to ARCHIVE tables; prune delivered domain outbox rows.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $batch = (int) config('storage_retention.batch_size');

        // Only processed raw events: received/processing/failed are the replay source and stay hot.
        // processed_at (falling back to created_at) so the window counts from when the event stopped
        // being replay-relevant, not from when it arrived.
        $raw = fn () => DB::table('raw_sensor_events')->where('status', 'processed')
            ->whereRaw('COALESCE(processed_at, created_at) < ?', [now()->subDays((int) config('storage_retention.raw_hot_days'))]);
        $audit = fn () => DB::table('audit_logs')
            ->where('created_at', '<', now()->subDays((int) config('storage_retention.audit_hot_days')));
        // Terminal: relayed (published) AND acknowledged by the browser consumer (delivered_at).
        $outbox = fn () => DB::table('domain_event_outboxes')->where('status', 'published')
            ->whereNotNull('delivered_at')
            ->where('created_at', '<', now()->subDays((int) config('storage_retention.domain_outbox_days')));

        $rows = [
            ['raw_sensor_events -> archive', $dry ? $raw()->count() : $this->move($raw, 'raw_sensor_events', 'raw_sensor_events_archive', $batch)],
            ['audit_logs -> archive', $dry ? $audit()->count() : $this->move($audit, 'audit_logs', 'audit_logs_archive', $batch)],
            ['domain_event_outboxes deleted', $dry ? $outbox()->count() : $this->prune($outbox, $batch)],
        ];

        $this->table(['Action', $dry ? 'Would affect' : 'Affected'], $rows);

        return self::SUCCESS;
    }

    private function move(\Closure $query, string $from, string $to, int $batch): int
    {
        $cols = implode(',', array_map(fn ($c) => "`$c`", Schema::getColumnListing($from)));
        $total = 0;

        while (($ids = $query()->orderBy('id')->limit($batch)->pluck('id'))->isNotEmpty()) {
            DB::transaction(function () use ($from, $to, $cols, $ids): void {
                $in = implode(',', array_fill(0, $ids->count(), '?'));
                DB::insert("INSERT INTO `$to` ($cols, `archived_at`) SELECT $cols, ? FROM `$from` WHERE id IN ($in)", [now(), ...$ids->all()]);
                DB::table($from)->whereIn('id', $ids)->delete();
            });
            $total += $ids->count();
        }

        return $total;
    }

    private function prune(\Closure $query, int $batch): int
    {
        $total = 0;
        while (($ids = $query()->orderBy('id')->limit($batch)->pluck('id'))->isNotEmpty()) {
            $total += DB::table('domain_event_outboxes')->whereIn('id', $ids)->delete();
        }

        return $total;
    }
}
