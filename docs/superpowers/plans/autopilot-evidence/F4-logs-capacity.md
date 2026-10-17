# F4 evidence: logs, capacity guard, compose limits

## Changes
- `back/.env.example`: LOG_STACK=daily, LOG_LEVEL=info, LOG_DAILY_DAYS=3, LOG_ARCHIVE_DAYS=30.
- `back/config/logging.php`: `archive_days`; `daily` days and `json` maxFiles are `max(2, LOG_DAILY_DAYS)` (default still 14, existing test pins it).
- `iot:logs:archive [--compress|--purge] [--path=]`: gzips `*.log`/`*.json` with mtime > 1 day and not today's file (zlib stream, original mtime preserved on the .gz); purges `*.gz` older than `logging.archive_days`. `audit-*.json` is skipped (Monolog keeps it 90 days). Monolog `RotatingFileHandler` globs `<name>-*.<ext>` so `.gz` files are never counted nor deleted by it.
- `iot:capacity:check`: disk of `storage_path()`, MySQL schema size vs `CAPACITY_DB_MAX_MB` (default 10240; skipped on sqlite), Redis `used_memory`/`maxmemory` (skipped if unavailable or maxmemory=0). `--warn=80 --critical=90`. Log::warning/critical every run; mail via `CapacityAlertMail` to the same recipient as danger alerts (`Alert::applyMailSettings()`, extracted from `sendDangerAlertEmail`, SystemSetting mail_*), deduped per metric 6 h with `Cache::add`. Exit code 2 on CRITICAL. Prints a table.
- `iot:cdc:trim` (extra, see decision below): hourly XTRIM MINID of the two Debezium CDC streams.
- `routes/console.php`: compress 00:30 daily, purge 1st and 15th 01:00, capacity every 15 min, cdc trim hourly.
- `back/phpunit.xml`: LOG_CHANNEL=null. Nothing in tests reads log files (ObservabilityConfigTest only inspects config).
- `docker-compose.yml`: `x-logging` (json-file 10m x 5) on all 12 services; `scheduler` service (profile workers, `*worker_env`, `schedule:work`); redis `--maxmemory 512mb --maxmemory-policy noeviction`; db `--performance-schema=OFF --innodb-buffer-pool-size=256M --max-connections=60`; `INGESTION_RAW_EVENTS_MIN_AGE_MS` and `DOMAIN_EVENTS_MIN_AGE_MS` default 259200000 (also added to `worker_env`, which lacked them: the cdc consumer publishes domain events). `lab_esp32/broker/docker-compose.broker.yml` NOT touched (F1 owns it).
- `/api/health` summary: skipped (would expose internals publicly; the command table and logs cover it).

## Stream retention decision
- `MIN_AGE_MS` is applied as `XADD ... MINID ~ now-age` on `iot.raw-events` / `iot.domain-events`. Consumers read with XREADGROUP/XAUTOCLAIM and ack; durable sources are MySQL (`raw_sensor_events`, outbox tables). Nothing reads entries older than 72 h except a consumer that was down more than 72 h; in that case the trimmed stream entries are gone but the MySQL rows remain. Set 72 h.
- Debezium Redis sink: `cdc/application.properties` has no trim/maxlen setting and the sink does not trim, and the app deliberately never XTRIMs (config comment). With noeviction these streams would fill Redis. Added `iot:cdc:trim`: cutoff = min(now-72h, group last-delivered-id, oldest pending id), so nothing undelivered or unacked is dropped. Verified against a real local Redis: entries 1000,2000 (acked) trimmed; 3000 (pending) and 4000 (undelivered) kept.

## Verification
- Full suite: `Tests: 561 warnings, 6 passed` summary line truncated by the pre-existing Throwable-use warnings; 0 failures (new: ArchiveLogsTest 1, CapacityCheckTest 2).
- `docker compose config -q`: OK.
- `php artisan schedule:list`:
```
15   3 *    * *  iot:storage:tier
30   0 *    * *  iot:logs:archive --compress
0    1 1,15 * *  iot:logs:archive --purge
*/15 * *    * *  iot:capacity:check
0    * *    * *  iot:cdc:trim
```
- Sample `iot:capacity:check --warn=1` (dev Mac, sqlite, no Redis maxmemory):
```
| disk         | 230039 MB | 239072 MB | 96.2% | CRITICAL |
| mysql_schema | -         | -         | -     | skipped  |
| redis_memory | -         | -         | -     | skipped  |
```
(the dev disk really is at 96 %).
