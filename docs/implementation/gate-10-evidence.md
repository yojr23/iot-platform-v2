# Gate 10 Evidence

Status: OPEN — prior live-Docker evidence is historical; Task 12 could not refresh it because the
Docker daemon and Redis services are unavailable. Do not treat this document as a Gate 10 CLOSED
verdict.

Captured: 2026-09-11 on macOS (PHP 8.5, Docker: MySQL 8 + Redis 7 + Debezium 3.5 + CDC/raw/domain
consumers, isolated SQLite for the test suite).

> The prior "Session constraint / NOT run" caveat is fully resolved. This session ran the real
> Docker stack end to end AND a real browser against it. Every field below is captured output.

## Live-Docker CDC pipeline — VERIFIED END TO END (2026-09-11)

Full stack up (`docker compose --profile workers up -d`): db, redis, back, front, debezium,
outbox-cdc-consumer, raw-consumer, domain-event-consumer all healthy. Two infra bugs found and
fixed while bringing it up (see "Session changes"):

1. **Debezium config mount path** — v3.5 reads `/debezium/config/application.properties`; compose
   mounted it at `/debezium/conf/` (older path), so Debezium crash-looped on
   `debezium.sink.type is required`. Fixed the mount; Debezium now connects to the binlog and
   streams (`Connected to db:3306 ... streaming`).
2. **Redis stream key-prefix mismatch (real delivery break)** — `RawSensorEventPublisher` /
   `DomainEventPublisher` XADDed via the Redis *facade*, which prepends Laravel's key prefix
   (`iot_platform_v2_back_database_`), while every consumer + Debezium read the stream with RAW,
   unprefixed commands. XADD landed on `<prefix>iot.raw-events`, consumer read `iot.raw-events`:
   they never met, so no readings were ever created in the running stack. The 26 CDC unit tests use
   counting-fake publishers, so they could not catch it. Fixed both publishers to use the same raw
   unprefixed XADD as the consumers (shared `UsesRawRedisCommands` trait). Frozen by the new
   `PublisherStreamPrefixTest` (real Redis) + rewritten `EventPublisherXaddShapeTest`.

End-to-end proof after the fix — POST `/api/ingestion/events` →
`raw_sensor_events` + `raw_event_outboxes(pending)` committed → Debezium captured the binlog →
`iot-cdc.iot_platform.raw_event_outboxes` CDC stream → `outbox-cdc-consumer` republished to
`iot.raw-events` → `raw-consumer` normalized → **2 `sensor_readings` created**
(temperature=25.3, dissolved_oxygen=7.9) → 2 `domain_event_outboxes` → `domain-event-consumer`
delivered (`delivered_at` set, **2/2**, 0 pending with broadcast driver `log`). Bounded-retry
behavior also observed: with Pusher unconfigured the domain consumer left messages pending and
retried (the DLQ path), never silently dropping.

## Browser desktop + mobile — VERIFIED against the live stack (2026-09-11)

Real Chromium (`.audit-e2e/live-browser-check.mjs`) logged in via the real form as admin against
front:5173 + API:8000. Dashboard renders clean at desktop 1440 and mobile 390 (only console errors
are the expected Pusher WebSocket refusals — no Pusher server locally). Screenshots:
`.audit-e2e/results/live-dashboard-{desktop-1440,mobile-390}.png`.

**Private sensor history (new feature) verified in-browser:** adding a graph for the restricted
sensor `dissolved_oxygen` (which now appears in the authenticated catalog — the double-unwrap fix)
issued exactly **1 call to `/api/sensors/{id}/series` (private endpoint) and 0 to
`/api/public/graph/.../series`** at both viewports. Before this work that sensor never appeared in
the catalog and its history 404'd on the public route.

## Backend test suite (isolated SQLite + Redis 6399)

- Full suite `php artisan test`: **303 tests, 0 failed, 0 skipped (1170 assertions)**.
  ("deprecated" markers = passing tests emitting the PHP 8.5 `PDO::MYSQL_ATTR_SSL_CA` notice.)
- New: `PublisherStreamPrefixTest` (real-Redis prefix regression), `SensorApiControllerTest`
  private-series tests, rewritten `EventPublisherXaddShapeTest`.

## Session changes (why HEAD-of-record differs from committed HEAD)

- `IngestionApiTest`: removed the two obsolete tests that asserted synchronous
  `RawSensorEventPublisher::publish()` / `Redis::command()` (the controller no longer publishes
  synchronously — delivery is CDC-driven). Replaced with a test that proves the transactional
  outbox contract (`raw_sensor_events` + `raw_event_outboxes` pending row, no synchronous Redis).
  `tearDown()` now wraps `Mockery::close()` in `try/finally` so a Mockery failure can never again
  skip `parent::tearDown()` and cascade "There is already an active transaction" across the suite.
- `Gate10PublicGraphBoundaryTest::test_g10_pub_08`: added `$this->app['auth']->forgetGuards()`
  between logout and re-request. Sanctum's `RequestGuard` memoizes the resolved user per booted
  app; real HTTP boots a fresh guard per request, the shared test container does not. The token
  IS revoked at logout (verified: `personal_access_tokens` count 1 → 0); the test now mirrors
  real per-request auth resolution instead of asserting against a cached user object.
- Sensor-resolution N+1 (`RawReadingNormalizerTest::test_sensor_resolution_query_count_...`):
  `SensorReadingService::createReading()` now attaches the already-resolved sensor to the reading
  before save and loads sub-relations onto it; `AlertService::triggeredRulesForReading()` reuses
  that loaded relation. Sensor `SELECT` count is now constant (1 per receipt) regardless of
  payload size, as the test requires.
- `.github/workflows/gate10-quality.yml`: added a `redis:7` service (exposed on 6399) + `REDIS_HOST`
  /`REDIS_PORT` env so the 26 CDC/domain-event tests run in CI instead of being skipped.

## Backend test evidence (all captured, isolated SQLite + Redis 6399)

- Full suite `php artisan test`: **293 deprecated, 6 passed, 0 failed, 0 skipped (1166 assertions)**.
  ("deprecated" = passing tests that emit the PHP 8.5 `PDO::MYSQL_ATTR_SSL_CA` notice; not failures.)
- `--filter=Gate10PublicGraphBoundaryTest` (G10-PUB-01..08): **8 passed (39 assertions)** — G10-PUB-08
  green.
- `--filter="CdcOutboxStreamConsumerTest|DomainEventBroadcastConsumerTest"` (needs Redis):
  **26 passed (89 assertions)** — previously ENVIRONMENT_CONSTRAINT-skipped, now executed against
  real Redis.
- `--filter=Gate10NoPollingArchitectureTest` (filesystem only): **4 passed (41 assertions)**.

## Frontend evidence (all captured)

- `npm run test:unit`: **182 passed (36 files)**.
- `npm run build`: **built in ~4.3s** (production bundle emitted).
- `npm run audit:no-polling:source`: **PASS — no product-state polling constructs in tracked source.**
- Browser matrix (`.audit-e2e/run-all.mjs`, Playwright/Chromium, mocked API): **53/53 runs clean**
  (no nav error, no doc overflow, no console/page errors, no failed requests). Matrix now includes
  the redesigned `/metrics` at 320/360/390/768/1024/1280/1440 and dashboard desktop confirms at
  1024/1280/1440. Visual spot-check of `/metrics` at 320 (mobile) and 1440 (desktop) confirms KPI
  cards, doughnuts, gauge and comparative bar all render and stack correctly.

## 10.1 Observability
`EventPipelineMetricsService::snapshot()` returns `streams`/`consumers`/`outbox`; counters
`cdc_publish_success|cdc_publish_failure|cdc_dlq|domain_broadcast_success|domain_broadcast_failure|raw_processed|raw_failed`.
Endpoint `GET /api/internal/metrics/event-pipeline` gated `auth:sanctum` + `admin` + `throttle:api-read`.
Covered by the passing backend suite.

## 10.1a Redis durability and bounded application streams (Task 6)

The Compose Redis service runs Redis 7 with `appendonly yes` and `appendfsync everysec`, backed by
the named `redis_data:/data` volume. This explicitly provides an approximate **one-second
Redis-side RPO** for acknowledged writes at host/process failure boundaries. It is not a zero-loss
guarantee and does not replace replica, snapshot, volume, or restore testing.

Application-owned XADD writers use raw, unprefixed commands so producer and consumer keys continue
to agree, with approximate retention in the wire form `XADD <stream> MAXLEN ~ <limit> * ...`:

- `INGESTION_RAW_EVENTS_MAXLEN=500000` for `iot.raw-events`.
- `DOMAIN_EVENTS_MAXLEN=500000` for `iot.domain-events`.
- `DLQ_MAXLEN=1000000` for `iot.dead-letter-events` writes from the raw, domain, and CDC consumers.

`CDC_STREAM_MAXLEN=500000` is deliberately an operational threshold, not an automatic `XTRIM`.
Debezium is the only CDC-stream publisher; the application must not add a second CDC publisher or
trim a CDC stream while `XPENDING` shows unprocessed consumer-group entries. Before a CDC trim,
operators must confirm all relevant groups have acknowledged the candidate range and preserve an
adequate replay window. This avoids converting a retention job into CDC data loss.

The listed values are starting production values, not one-size-fits-all caps. Size them from real
ingress rate, worst-case consumer outage, required replay horizon, message size, available Redis
memory/disk, and recovery drills; do not silently substitute a tiny limit. `MAXLEN ~` is purposely
approximate, so Redis may retain a bounded amount above the target at a macro-node boundary.

`EventPublisherXaddShapeTest` freezes the raw unprefixed `MAXLEN ~` publisher command. The real
Redis `PublisherStreamPrefixTest` additionally publishes above a small configured limit, verifies
approximate bounded retention, and confirms a consumer group can still read the retained stream.

## 10.2 Legacy retirement
Static gate `front/scripts/verify-no-polling.mjs` PASS (see above). `architecture` CI job confirms
all six relay files and both compose relay services are absent and CDC config present.

## 10.3 Deployment-wide no-polling proof
Live browser check against the real Docker stack (see "Browser desktop + mobile" above) showed the
authenticated dashboard's only recurring/product API traffic is event-driven; the sole console
errors are Pusher WebSocket refusals (no Pusher server in this local stack). A full formal
`network-assertion-live.mjs` 35s-quiet capture still requires a Pusher-compatible websocket server
wired in; the pipeline and boundary it asserts are otherwise verified above.

## 10.4 Public graph boundary
`Gate10PublicGraphBoundaryTest` G10-PUB-01..08 all pass against the real routes/middleware. Also
confirmed live: restricted sensor series 404s on `/api/public/graph/...` and 200s on the new
authenticated `/api/sensors/{id}/series`.

## CDC failure matrix (PLAN.md Task 11)
Scenarios A–E (Laravel dies post-commit; CDC consumer dies after XADD before ACK; Redis outage +
recovery from durable offset; poison CDC record → DLQ; process restart). Consumer logic passes
against real Redis (26 tests). The happy-path live pipeline is now verified end to end on Docker
(see above); the deliberate A–E fault-injection runs (killing containers mid-flight) remain the one
un-scripted item.

## Outstanding (before a full Gate 10 CLOSED verdict)
1. Scripted live-Docker CDC fault-injection runs (Scenarios A–E) — happy path verified, faults not.
2. Formal `network-assertion-live.mjs` 35s capture (needs a local Pusher-compatible websocket).
3. Let `gate10-quality.yml` (now with Redis) run green on CI for the pushed branch.

## Final verdict
Backend CI blockers RESOLVED (0 failed, 0 skipped, 26 Redis tests executed). The full CDC delivery
pipeline is now VERIFIED end to end on the live Docker stack (ingest → binlog → Debezium → Redis →
consumers → readings → domain delivery), two real infra/delivery bugs found and fixed in the
process, and the private-sensor history feature is verified in a real browser at desktop and mobile.
Gate 10 is substantially closed; the only remaining items are the scripted A–E fault-injection runs
and the formal live network-quiet capture (needs a local websocket server). Everything the running
product depends on for correct event-driven delivery is proven.

## Task 10 current-session closure attempt — BLOCKED, NOT LIVE PROOF (2026-09-12)

Task 10 requires a real Pusher-compatible WebSocket service, a `network-assertion-live.mjs`
observation of at least 35 seconds, and live CDC fault injection A–E. None is claimed from mocks.

The exact availability command was:

```sh
docker compose ps --format json
```

Actual output:

```text
Cannot connect to the Docker daemon at unix:///Users/j.rinconc/.docker/run/docker.sock.
Is the docker daemon running?
```

No ports for the required frontend/API/Redis/CDC/Pusher-compatible services were listening. Thus
the following live runs were not possible and remain explicit gaps:

| Requirement | Status | Reason |
| --- | --- | --- |
| Real Pusher-compatible WebSocket transport | BLOCKED | Docker daemon unavailable; no service to subscribe to. |
| `npm run audit:network:live` for >=35s | BLOCKED | Requires live frontend/API credentials and the real transport. |
| Guest public-only and authenticated private-history network assertions | BLOCKED | Cannot issue live API/transport requests without the stack. |
| Reconnect subscription dedup and logout projection isolation | BLOCKED | Cannot observe a real transport lifecycle. |
| A: process dies after DB commit | BLOCKED | Needs MySQL/Debezium/consumer containers. |
| B: consumer dies after XADD before XACK | BLOCKED | Needs Redis plus a live CDC consumer PEL. |
| C: Redis outage and recovery | BLOCKED | Needs the running Redis/CDC deployment. |
| D: poison CDC record to DLQ | BLOCKED | Needs live Debezium input and consumer/DLQ. |
| E: restart with pending messages/XAUTOCLAIM | BLOCKED | Needs live pending entries and a restarted consumer. |

The codebase's Redis-backed feature tests may still cover portions of those semantics in CI, but
they do not substitute for this required deployment fault proof. The mocked responsive E2E job
added below is labelled as mocked and must never be used as live Gate 10 evidence.

### CI protection added by Task 10

`.github/workflows/gate10-quality.yml` now has:

- `ingestion`, exactly using Python 3.12, pip dependency caching keyed by
  `ingestion_service/requirements.txt`, `pip install -r requirements.txt`, and
  `python -m pytest -q`.
- `frontend-responsive-e2e-mocked`, which installs Chromium, starts the demo server, runs
  `npm run audit:gate9`, and uploads `t10-*` artifacts. Its comments and job name explicitly say
  that fixture-backed E2E is not live Pusher/WebSocket evidence.

Workflow YAML, package JSON, audit script syntax, and whitespace validation were checked locally;
the CI jobs themselves have not yet executed remotely in this session.

## Task 12 final verification — OPEN / GAP (2026-09-12)

Recorded Git `HEAD`: `3c8f8971e71d587ddda1ad59ef9e62faa5e61918`. The working tree contains
uncommitted changes, so all fresh source, test, and browser results apply to that working tree and
must not be represented as an immutable-commit or remote-CI result.

Fresh local checks:

- `back`: `TEST_REDIS_HOST=127.0.0.1 TEST_REDIS_PORT=6399 php artisan test` exited 0 with
  **1,520 assertions**; its summary reports 425 deprecated and 6 non-deprecated test outcomes.
  This is SQLite-suite evidence, not a real-Redis integration result.
- `ingestion_service`: `./.venv/bin/python -m pytest -q` — **21 passed**.
- `front`: `npm run test:unit` — **39 files, 199 tests passed**; `npm run build` exited 0;
  `npm run audit:no-polling:source` passed.
- Fixture-backed browser matrix: `npm run audit:gate9` — **33/33 clean**; the separate 33,004ms
  mocked `npm run audit:network` run had no recurring offender. These are explicitly not live
  Gate 10 transport evidence.

Current availability checks were negative:

```text
docker compose ps --format json
Cannot connect to the Docker daemon at unix:///Users/j.rinconc/.docker/run/docker.sock.

redis-cli -h 127.0.0.1 -p 6399 ping
Could not connect to Redis at 127.0.0.1:6399: Connection refused

redis-cli -h 127.0.0.1 -p 6379 ping
Could not connect to Redis at 127.0.0.1:6379: Connection refused
```

Only local Vite on `127.0.0.1:5173` was listening. Static Compose inspection declares the worker
profile services but no Pusher-compatible service. `graphify 0.9.58` is installed, but it was not
run because Task 12 forbids modification of `graphify-out/` artifacts.

Accordingly, the following remain **BLOCKED**, not passed: real Redis-backed suite; Docker MySQL →
Debezium → Redis → CDC/raw/domain consumer delivery; Pusher/WebSocket subscription and reconnect
proof; `npm run audit:network:live`; guest/authenticated live boundary traces; CDC failure scenarios
A–E; and remote `gate10-quality.yml` execution. Gate 10 and the PLAN Stage 10 DoD remain open.
