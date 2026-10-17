# F3 storage tiering evidence

## Changes
- `back/database/migrations/2026_10_17_000001_create_storage_archive_tables.php`: `raw_sensor_events_archive` and `audit_logs_archive` (ENGINE=ARCHIVE on MySQL; plain `id` BIGINT, no indexes, extra `archived_at`); `ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8` on `sensor_readings` and `reading_projections` (down: DYNAMIC). MySQL-only DDL guarded by driver check.
- `back/app/Console/Commands/TierStorage.php`: `iot:storage:tier [--dry-run]`; batches of 1000 ids, `INSERT..SELECT` + `DELETE` per batch in one transaction.
- `back/config/storage_retention.php`, `.env.example` (RAW_HOT_DAYS=14, DOMAIN_OUTBOX_DAYS=7, AUDIT_HOT_DAYS=90).
- `back/routes/console.php`: `Schedule::command('iot:storage:tier')->dailyAt('03:15')->withoutOverlapping()`.
- `back/tests/Feature/TierStorageCommandTest.php` (4 tests).

## Decisions
- Raw age = `COALESCE(processed_at, created_at)`; only `status='processed'` moves. received/processing/failed never touched.
- Domain outbox terminal = `status='published' AND delivered_at IS NOT NULL` (relay done and browser consumer acked), age by `created_at`.
- FKs verified: `raw_event_outboxes.raw_sensor_event_id` cascadeOnDelete; `reading_projections.raw_sensor_event_id` nullOnDelete (no other FKs reference raw_sensor_events).
- sensor_readings and archive tables are never deleted from.

## Tests
- `--filter=TierStorageCommandTest`: 4 tests, 22 assertions pass.
- Full suite (sqlite): 6 passed + 558 warnings (pre-existing metadata-deprecation warnings), 0 failures.

## MySQL 8.0 real check (throwaway container, fresh `migrate --force`: all migrations OK, no Redis/trigger problems)
```
audit_logs_archive         Engine: ARCHIVE  Row_format: Compressed
raw_sensor_events_archive  Engine: ARCHIVE  Row_format: Compressed
reading_projections        Engine: InnoDB   Row_format: Compressed  Create_options: row_format=COMPRESSED KEY_BLOCK_SIZE=8
sensor_readings            Engine: InnoDB   Row_format: Compressed  Create_options: row_format=COMPRESSED KEY_BLOCK_SIZE=8
```
Last run, aged data (setup: processed 40d + outbox + projection; audit 200d; domain published+delivered 30d; pending 30d):
```
BEFORE: raw 4, raw_archive 3, raw_outbox 4, audit 2, audit_archive 0, domain_outbox 2, projections_null_raw 0
iot:storage:tier -> raw 1, audit 1, domain outbox deleted 1
AFTER:  raw 3, raw_archive 4, raw_outbox 3, audit 1, audit_archive 1, domain_outbox 1, projections_null_raw 1
```
Earlier run: dry-run reported 2 and changed nothing; run moved 2; rerun 0 (no-op). Archived payload JSON preserved (`{"a": 1}`), `archived_at` set. Container removed.
