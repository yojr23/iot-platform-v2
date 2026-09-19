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

## Real-MySQL suite run (Phase B/C/E on MySQL 8.0, not just SQLite)

Running the atomicity/authorization/bounds tests against real MySQL first
surfaced 4 failures SQLite had hidden: the `users_before_insert_block_is_admin`
trigger (MySQL-only) rejects the test factory's `is_admin => true`. Fixed the
fixtures to grant admin capability the sanctioned way (assign the seeded
`admin` role via `role_id`), matching production. After the fix: **17 pass on
real MySQL and 17 pass on SQLite** — B2's nested transaction (mapSensor inner
tx as a savepoint) is now proven correct on MySQL specifically, and the tests
are engine-portable.

## Phase O (partial) — fresh migration on real MySQL

`migrate:fresh --force` against MySQL 8.0 completed all migrations cleanly
(roles/permissions, temporal mappings, api-key-hash, reading projections,
audit logs) — confirms MySQL migration portability from a clean database.

---

## Live stack — ingestion vertical slice (HTTP tier, real running stack)

Brought up the real stack: MySQL (`db`), Redis (`redis`), Laravel API (`back`,
:8000, healthy, Laravel 12.69.2), Vue dev server (`front`, :5173). Redis
already carries the pipeline streams (`iot.raw-events`, `iot.domain-events`,
`iot-cdc.*`).

Live HTTP ingestion trace (correlation id `e2e-trace-1789650939`):

| Case | Request | Result |
|------|---------|--------|
| Valid event | `POST /api/ingestion/events` (valid `X-Ingestion-Token`) | **201** `event_id=5, duplicate:false`; verified `RawSensorEvent#5` + 1 `RawEventOutbox` (atomic) |
| Duplicate `source_event_id` | replay same id, different value | **200** `duplicate:true`, same `event_id=5`, raw-event count stays **1** (idempotent, no double-insert) |
| Wrong token | `X-Ingestion-Token: bad` | **401** |
| Missing token | no token header | **401** |
| Malformed payload | valid token, `{"topic":"t"}` (no `payload.sensors`) | **422** controlled |

This certifies the ingestion → RawSensorEvent → RawEventOutbox tier of the
vertical end to end on the live stack, plus the credential + idempotency +
validation matrix. The remaining tiers (MQTT broker → Python spool, and CDC →
Redis stream → consumers → broadcast → browser) still require the
`workers`-profile services + an MQTT broker (see below).

## Live stack — raw consumer + poison→DLQ (Phase H4, real Redis consumer group)

Brought up the `raw-consumer` (and `domain-event-consumer`) worker containers.
The `raw-process-v1` consumer group on `iot.raw-events` is live and processing.

Observed real state across raw events:

| Event | Node | Consumer outcome |
|-------|------|------------------|
| 2, 3, 4 | mapped node | `status=processed` — normalized into `SensorReading` |
| 5 | `E2E-PUB-1` (no canonical mapping) | retried **5×** → `max_attempts_exceeded` → routed to `iot.dead-letter-events`, `status=failed` |

**Phase H4 (poison → DLQ) certified live:** the unmapped event 5 was retried
to the attempt ceiling, then dead-lettered with a full diagnostic — DLQ entry
carries `orig_stream=iot.raw-events`, `orig_id`, `attempts=5`,
`reason="max_attempts_exceeded: RawReadingNormalizer: no device found for
node_id [E2E-PUB-1]"`, and the original `payload_json`. Critically, the poison
event did **not** stall the partition — events 2/3/4 processed normally around
it. This is the required retry → terminal-failure → DLQ → ACK-source behavior.

## Full stack — MQTT→broadcast vertical trace (Phase I) — CERTIFIED

Brought up the COMPLETE stack: db, redis, back, front, **debezium** (streaming
MySQL binlog → Redis CDC), **outbox-cdc-consumer**, **raw-consumer**,
**domain-event-consumer**, **ingestion** (Python), and a **mosquitto** MQTT
broker on :1883.

Published one uniquely-identified MQTT message (`mac-e2e-1789704069`, value
43.219, node `lab_postgrado_nodo_01`) and traced it through EVERY backend
boundary:

```
MQTT publish (mosquitto)
  → Python on_message + validate + durable spool     [ingestion log: "Raw event durably queued"]
  → HTTP POST /api/ingestion/events                  [ingestion log: "Backend delivery completed"]
  → RawSensorEvent #8 (status=processed) + RawEventOutbox (atomic)
  → Debezium binlog capture → Redis iot-cdc.* stream
  → outbox-cdc-consumer → iot.raw-events
  → raw-consumer → RawReadingNormalizer → SensorReading #8
  → domain_event_outbox #6
  → Debezium → domain CDC → iot.domain-events
  → domain-event-consumer → event(NewSensorReading)  [outbox delivered_at set]
```

The domain outbox payload was **enriched**: `public_at_occurrence=true`,
`sensor_name=temperature` — confirming the durable self-contained event fix
(commit `127affc`) works through the normalizer write path, not just direct API.
Final browser WS frame uses the Pusher-compatible broadcaster; in this local
run `BROADCAST_CONNECTION=log`, so the browser-visible frame itself is covered
separately by the GATE 10 LIVE no-polling capture (real Echo subscription).

## Full stack — event-recovery fault matrix (Phase H) — CERTIFIED

Real fault injection against the running stack:

| Fault | Method | Result |
|-------|--------|--------|
| **H1 Debezium restart** | `docker compose restart debezium` with an event committed during downtime | Debezium resumed from its **durable Redis offset** (`mysql-bin.000006/19618`, not from scratch); the during-restart event fully processed (raw #9 `processed`, reading #9). **Zero loss.** |
| **H2 Redis restart** | `docker compose restart redis` right after an MQTT publish | Streams survived (persistence); consumers auto-reconnected; the during-restart event recovered end to end (raw #10 `processed`, reading #10, domain `delivered=yes`). DB receipt is the durable source. **Zero loss.** |
| **H3 XAUTOCLAIM worker takeover** | stopped raw-consumer, created an orphaned pending entry owned by a dead consumer, restarted raw-consumer | The replacement worker **reclaimed** the idle pending entry (pending 1→0) and processed it to terminal disposition (`dlq=1` for the deliberately-unresolvable payload). No message stuck on a dead consumer. |
| **H4 poison→DLQ** | unmapped-node event (earlier) | retried 5× → `max_attempts_exceeded` → `iot.dead-letter-events` with full diagnostic, without stalling the partition. |

## Phases still requiring more (honestly scoped)

- **J/K** live no-polling browser capture — **GATE 10 LIVE: PASS** (60s, real stack, `docs/evidence-gate10-live-network.json`).
- **Full MQTT→BROWSER single frame** — backend vertical + browser realtime are each certified; joining them into one continuous MQTT-value-appears-in-browser frame needs a Pusher-compatible server (soketi) wired in place of `BROADCAST_CONNECTION=log`.
- **Broadcast crash windows (H5)** — not yet isolated (needs the soketi broadcaster to observe duplicate-delivery semantics).
- **L** desktop/mobile device QA — mocked responsive matrix green in CI; real-device visual acceptance not run.
- **Q** branch protection — needs GitHub repo admin.
- **R** CI dependency-security gate — **DONE** (`.github/workflows/gate10-quality.yml`).

Honest status: source-fixable correctness/authorization/perf phases are done
and tested; live-infra certification is pending a full stack run.

---

## FINAL LIVE CERTIFICATION — 2026-09-19 (Mac)

**Application freeze SHA:** `4b14825` (`fix(ingestion): normalize RawSensorEvent received_at to app timezone`). Live gates re-run to evidence on `96d9523`/`76f12c9` (byte-identical application source). Repo HEAD after evidence/graphify: `3953fb5`.

**Environment:** Darwin 23.5.0; PHP 8.5.2 (Docker back PHP 8.4); MySQL 8.0/8.4 (Docker); Redis 7 (AOF); Debezium Server 3.5.2; Laravel Reverb; Mosquitto 2.1.2 (host, persistence on, `clean_session=false`, client id `iot-platform-v2-ingestion`, QoS1); Node 18.20.8; Docker 27.3.1. Full `--profile workers` topology + host Mosquitto + production preview (`127.0.0.1:4173`).

| Gate | Command / harness | Result | Evidence |
|------|-------------------|--------|----------|
| Backend suite | `php artisan test` (SQLite in-mem) | 482 pass / 52 skip | — |
| Ingestion suite | `pytest -q` | 49 pass | — |
| Frontend unit | `vitest run` | 341 pass | — |
| Production build | `npm run build` (release env) | PASS | `front/dist` |
| No-polling source | `audit:no-polling:source` | PASS | — |
| MySQL fresh migrate | `migrate:fresh --seed` (real MySQL) | PASS | — |
| MySQL concurrency | `tests/concurrency/run_mapping_race.sh 1 1 20` | 20/20 invariant held | — |
| Gate 10 A–E fault matrix | `scripts/gate10/fault_injection.py --scenario all` | A/B/C/D/E PASS | `.audit-e2e/results/gate10-faults-96d9523….json` |
| MQTT durability (subscriber-down) | `mqtt_durability.sh` | 10 published / 10 recovered / 0 lost / 0 dup | `.audit-e2e/results/mqtt-durability-<sha>.json` |
| MQTT durability (spool-crash) | manual orchestration | delivered after restart; spool row cleared only after ack | same |
| MQTT → browser E2E | `front/.audit-e2e/mqtt-to-browser-live.mjs` | PASS — rendered in DOM ~2.6s, 0 recurring REST GET | `front/.audit-e2e/results/mqtt-browser-live-<sha>.json` |
| H5 duplicate-delivery (browser) | `front/.audit-e2e/h5-broadcast-crash-live.mjs` | 2 physical frames (reading 74, event 44) → 1 logical row, XPENDING 0, DLQ +0 | `front/.audit-e2e/results/h5-broadcast-crash-<sha>.json` |
| Live no-polling / auth | `front/.audit-e2e/network-assertion-live.mjs` | PASS — guest clean, logout 200 + stale token 401, 0 recurring offenders | `front/.audit-e2e/results/gate10-live-network.json` |
| Gate 9 responsive (mocked) | `audit:gate9` on preview | 35/35 | `front/.audit-e2e/results/task10-summary.json` |
| Gate 9 responsive (live screenshots) | `front/.audit-e2e/production-screenshots.mjs` | 98 PNGs, 0 auto findings, manual spot-check clean | `front/.audit-e2e/results/screenshots/` |
| composer audit | — | 0 advisories | — |
| pip-audit | — | 0 vulnerabilities | — |
| npm audit | — | 4 dev-only (vite/esbuild/vitest), accepted residual | — |

**Two source defects found only by live certification, both fixed:**
1. `3dc771b` — Gate-10 fault harness read `docker logs` stdout while the checkpoint marker is on stderr → scenarios B/E had never passed to committed evidence.
2. `4b14825` — `RawSensorEvent.received_at` stored UTC digits read back as APP_TIMEZONE, pushing every MQTT-sourced `reading_time` ~5h into the future so the browser clock-drift guard dropped it and no live telemetry rendered. Fixed with an app-timezone setter + `RawSensorEventReceivedAtTimezoneTest`.

**Accepted limitations:** 4 dev-only npm advisories (never in the production bundle); pre-existing repo-wide Pint style debt in ~130 unmodified files (no new violations this session).

## FINAL CI (M25)

GitHub Actions `gate10-quality` run **#70**, pushed commit `4ab5e3f`, 2m 22s — **6/6 GREEN**:

| Job | Result |
|-----|--------|
| backend | PASS |
| ingestion | PASS |
| architecture | PASS |
| frontend | PASS |
| frontend-responsive-e2e-mocked | PASS |
| dependency-security | PASS |

The run corresponds to the final committed repository state (`4ab5e3f`), on top of application freeze `4b14825`.

**PLAN: CLOSED. Gate 9: CLOSED. Gate 10: CLOSED.**
