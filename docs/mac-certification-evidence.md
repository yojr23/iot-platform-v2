# Mac Certification Evidence — `refraccion`

Reproducible Mac evidence for the closure/hardening campaign. Starting SHA
`43d6f96`. All backend runs use PHP 8.5 (SQLite in-memory for the suite, real
MySQL 8.0 for concurrency + EXPLAIN).

## Baseline (pre-change)

| Suite | Result |
|-------|--------|
| Backend `php artisan test` | 470 pass / 1672 assertions, 0 fail |
| Frontend `npm run test:unit` | 47 files / 262 tests pass |
| Frontend `npm run build` | PASS |
| `audit:no-polling:source` | PASS |
| Docker compose | daemon initially down; `db` (MySQL 8) later brought up |

## Phase A — Dependency security

| Ecosystem | Before | After | Action |
|-----------|--------|-------|--------|
| Backend `composer audit` | 44 advisories / 13 packages | **0** | `composer update` transitive + `laravel/framework` v12.10.2 → v12.69.2 |
| Frontend `npm audit` | 5 (2 high, 3 moderate) | 3 moderate (dev-only) | `npm audit fix` bumped `immutable` (high) |

Fixed high-severity: Laravel CRLF injection in default email rule
(`<12.60.0`), signed-URL path confusion, guzzle/commonmark/symfony chain.
Residual: `vitest`/`esbuild` moderate — **dev toolchain only, not shipped**;
fix needs a vitest major bump. Reviewed exception, tracked.

Post-fix suite: backend 431 pass / 45 skip (Laravel 12.69 also cleared the
PHP-8.5 `PDO::MYSQL_ATTR_SSL_CA` deprecation notices), frontend 262 pass,
build PASS, no-polling PASS.

## Phase C — Authorization consistency (SEC-RT-002)

**Bug found:** the private `sensor.{id}` WebSocket channel delivers reading
telemetry (`NewSensorReading`: value + reading_time) but authorized on
`sensor.view`, while REST reading endpoints gate on `sensor_reading.view`. A
user with `sensor.view` but not `sensor_reading.view` received live telemetry
over WS that REST denies (REST/WS divergence).

**Fix:** `ResourceAccessService::canViewSensorReadings()` (single telemetry
authority = `sensor_reading.view`); `routes/channels.php` `sensor.{id}` routes
through it. Four-cell matrix (view × reading.view) + WS authorize/deny parity
tests. Suite 437 pass.

## Phase B — Transactional integrity (SEC-TX-001..004)

| Item | Bug | Fix |
|------|-----|-----|
| B1 device provisioning | `Device::create` + status log, no tx → orphan device + credential | `DeviceService::createDevice` wraps both; `store()` routes through it |
| B2 sensor provisioning | `Sensor::create` + `mapSensor`, no outer tx → sensor with no canonical mapping | `store()` wraps both in one transaction (mapSensor inner tx nests as savepoint) |
| B3 device update | metadata `update()` then `changeStatus()` → partial commit on status failure while returning 500 | both wrapped in one transaction |
| B4 device delete | `statusLogs()->delete()` then `delete()`, no tx → history lost on device-delete failure | wrapped atomically |

Forced-failure regression test per item. Suite 443 pass.

## Phase E — allReadings global bounds

**Bug found:** `limit` applied **per sensor** → total work = sensor_count ×
per_sensor_limit, no global ceiling; malformed `from`/`to` threw from
`Carbon::parse` into a generic 500.

**Fix:** validate `from`/`to`/`limit` → controlled **422**;
`ALL_READINGS_GLOBAL_ROW_BUDGET = 5000` hard global ceiling; per-sensor limit
= `floor(budget / sensorCount)` (min 1). Tests: 422 on
malformed/reversed/zero, global total bound, per-sensor slice shrinks as
sensor count grows. Suite 447 pass.

## Phase D — MySQL mapping concurrency (real MySQL, not SQLite)

Harness: `back/tests/concurrency/` — two parallel PHP workers call the real
`SensorMappingService::mapSensor()` for one identity, barrier-synchronized,
mappings truncated each iteration to force the **empty-set first-ever race**.

```
iterations=100 violations=0
RESULT: PASS (invariant COUNT(open)=1 held every iteration)
transaction_isolation = REPEATABLE-READ
```

**Mechanism:** `lockForUpdate()` over the `dsm_temporal_lookup_idx` range
acquires InnoDB next-key/gap locks that serialize even the empty-set insert —
the second writer blocks until the first commits, then closes the
now-visible interval before inserting. Correct on MySQL; SQLite cannot prove
this (no gap locks). No code change required.

## Phase E/F — EXPLAIN evidence (real MySQL, 20k readings)

Bounded reading query (`sensor_id`, 24h window, `ORDER BY reading_time DESC
LIMIT 833`):

```
type=range  key=sensor_readings_sensor_time_id_idx  Extra=Using index condition; Backward index scan
EXPLAIN ANALYZE: Index range scan (reverse), actual time=0.398..2.5, rows=833
```

Index-backed range scan, no full-table scan, no filesort, 833 rows in ~2.5ms.
The Phase E global bound is served by the composite
`(sensor_id, reading_time, id)` index.

## Phase O (partial) — fresh migration on real MySQL

`migrate:fresh --force` against MySQL 8.0 completed all migrations cleanly
(roles/permissions, temporal mappings, api-key-hash, reading projections,
audit logs) — confirms MySQL migration portability from a clean database.

---

## Phases requiring the full live stack (not yet certified here)

These need the complete Docker stack (Debezium, MQTT broker, WebSocket
broadcaster, browser automation) and, for governance, GitHub admin rights.
They are **not** claimed as passed:

- **H** event-recovery fault matrix (Redis/Debezium restart, XAUTOCLAIM, poison→DLQ, broadcast crash windows)
- **I** full MQTT→browser vertical trace
- **J/K** live realtime + 60s no-polling browser capture (HAR)
- **L** desktop/mobile device QA
- **Q/R** branch protection + CI dependency gate (needs repo admin)

Honest status: source-fixable correctness/authorization/perf phases are done
and tested; live-infra certification is pending a full stack run.
