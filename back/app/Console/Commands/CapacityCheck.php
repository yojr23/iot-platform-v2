<?php

namespace App\Console\Commands;

use App\Mail\CapacityAlertMail;
use App\Models\Alert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Disk / MySQL schema / Redis memory guard. WARN >= 80 %, CRITICAL >= 90 %; mails the alert recipient
 * (SystemSetting mail_to, same path as danger alerts) at most once per metric per 6 h.
 */
class CapacityCheck extends Command
{
    protected $signature = 'iot:capacity:check {--warn=80} {--critical=90}';

    protected $description = 'Check disk, database and Redis usage; log and email when thresholds are crossed.';

    /** @return array<string,array{0:float,1:float}|null> metric => [used, total] in bytes (null = unavailable) */
    protected function measure(): array
    {
        $dir = storage_path();
        $disk = ($t = @disk_total_space($dir)) ? [$t - disk_free_space($dir), (float) $t] : null;

        $db = null;
        if (DB::connection()->getDriverName() === 'mysql') {
            try {
                $used = (float) DB::scalar('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()');
                $db = [$used, 1048576.0 * (int) env('CAPACITY_DB_MAX_MB', 10240)];
            } catch (Throwable) {
            }
        }

        $redis = null;
        try {
            $info = Redis::connection()->info('memory');
            $info = $info['Memory'] ?? $info; // predis nests under the section name
            if ((float) ($info['maxmemory'] ?? 0) > 0) {
                $redis = [(float) $info['used_memory'], (float) $info['maxmemory']];
            }
        } catch (Throwable) {
        }

        return ['disk' => $disk, 'mysql_schema' => $db, 'redis_memory' => $redis];
    }

    public function handle(): int
    {
        $rows = [];
        $worst = 0;

        foreach ($this->measure() as $metric => $m) {
            if ($m === null) {
                $rows[] = [$metric, '-', '-', '-', 'skipped'];
                continue;
            }
            [$used, $total] = $m;
            $pct = $used / $total * 100;
            $level = $pct >= (float) $this->option('critical') ? 'CRITICAL' : ($pct >= (float) $this->option('warn') ? 'WARN' : 'OK');
            $rows[] = [$metric, round($used / 1048576).' MB', round($total / 1048576).' MB', round($pct, 1).'%', $level];

            if ($level === 'OK') {
                continue;
            }
            $worst = max($worst, $level === 'CRITICAL' ? 2 : 1);
            $detail = round($used / 1048576).' MB de '.round($total / 1048576).' MB';
            $level === 'CRITICAL'
                ? Log::critical("capacity: $metric $detail", ['pct' => $pct])
                : Log::warning("capacity: $metric $detail", ['pct' => $pct]);

            if (Cache::add("capacity-alert:$metric", 1, now()->addHours(6))) {
                try {
                    if ($to = Alert::applyMailSettings()) {
                        Mail::to($to)->send(new CapacityAlertMail($metric, $level, $pct, $detail));
                    } else {
                        Log::warning('capacity: no mail recipient configured');
                    }
                } catch (Throwable $e) {
                    Log::error('capacity: alert mail failed', ['exception' => $e->getMessage()]);
                }
            }
        }

        $this->table(['metric', 'used', 'total', 'pct', 'status'], $rows);

        return $worst === 2 ? 2 : self::SUCCESS;
    }
}
