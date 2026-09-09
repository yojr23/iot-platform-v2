<?php

namespace App\Console\Commands;

use App\Models\SensorReading;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pre-Stage-6 Task 3 (PLAN.md Stage 6.0A preflight) — read-only diagnostic for the GAPs
 * `docs/implementation/reading-time-semantics.md` records: MySQL session/global `time_zone`
 * plus `NOW()`/`UTC_TIMESTAMP()`, and a raw-vs-cast comparison of the most recent
 * `sensor_readings` rows. Exists so closing those GAPs is one command against a real
 * MySQL-backed environment instead of ad-hoc SQL copy/paste.
 *
 * Existing code reused: `DB` facade / `SensorReading` model, same as every other read path in
 * this codebase — no new query/connection abstraction. Never mutates data (task requirement).
 * Existing owner retired/delegated: n/a.
 * Compatibility window: n/a.
 */
class InspectReadingTimeSemantics extends Command
{
    protected $signature = 'diagnostics:reading-time-semantics {--limit=10 : Most recent rows to inspect}';

    protected $description = 'Read-only probe of sensor_readings.reading_time storage/timezone semantics (Pre-Stage-6 Task 3).';

    public function handle(): int
    {
        $this->line('config(app.timezone) = ' . config('app.timezone'));

        $driver = DB::connection()->getDriverName();
        $this->line('DB connection driver = ' . $driver);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $row = DB::selectOne(
                'SELECT @@session.time_zone AS session_tz, @@global.time_zone AS global_tz, NOW() AS db_now, UTC_TIMESTAMP() AS db_utc_now'
            );
            $this->table(['session_tz', 'global_tz', 'db_now', 'db_utc_now'], [(array) $row]);
        } else {
            $this->warn("DB driver is '{$driver}', not mysql/mariadb — session/global time_zone probe skipped. See docs/implementation/reading-time-semantics.md GAP-1 to close this against real MySQL.");
        }

        $limit = max(1, (int) $this->option('limit'));
        $rows = DB::table('sensor_readings')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'sensor_id', 'reading_time', 'created_at']);

        if ($rows->isEmpty()) {
            $this->warn('No sensor_readings rows to inspect.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'sensor_id', 'raw reading_time', 'raw created_at', 'cast reading_time (ISO8601)'],
            $rows->map(function ($row) {
                $cast = SensorReading::find($row->id)?->reading_time?->toIso8601String();

                return [$row->id, $row->sensor_id, $row->reading_time, $row->created_at, $cast];
            })->all(),
        );

        return self::SUCCESS;
    }
}
