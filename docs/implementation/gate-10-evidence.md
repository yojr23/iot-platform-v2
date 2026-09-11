# Gate 10 Evidence

Status: BACKEND VERIFIED — live Docker CDC failure matrix still pending

HEAD: 5a2ff9ee5517d613069b9843fea13d2dabca6538 (+ uncommitted fixes for the obsolete
IngestionApiTest, the G10-PUB-08 harness artifact, the sensor-resolution N+1, and the
gate10-quality.yml Redis service — see "Session changes" below).

Captured: 2026-09-11T17:50Z on macOS (PHP 8.5, Redis 7 on 127.0.0.1:6399, isolated SQLite).

> The prior "Session constraint / NOT run" caveat is resolved: this session had a reachable PHP
> toolchain and a real Redis server, so every backend field below is real captured output, not
> prose. The remaining unverified block is the live-Docker CDC failure matrix (Scenarios A–E),
> which needs Debezium + MySQL binlog running — see "Outstanding".

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

## 10.2 Legacy retirement
Static gate `front/scripts/verify-no-polling.mjs` PASS (see above). `architecture` CI job confirms
all six relay files and both compose relay services are absent and CDC config present.

## 10.3 Deployment-wide no-polling proof
`network-assertion-live.mjs` (no mocks, full real stack) is the accepted live proof and still
requires a running back+front+Redis+Debezium stack. NOT captured this session (no Docker). The
mocked matrix above is explicitly NOT accepted as the Gate-10 live proof.

## 10.4 Public graph boundary
`Gate10PublicGraphBoundaryTest` G10-PUB-01..08 all pass against the real routes/middleware.

## CDC failure matrix (PLAN.md Task 11)
Scenarios A–E (Laravel dies post-commit; CDC consumer dies after XADD before ACK; Redis outage +
recovery from durable offset; poison CDC record → DLQ; process restart). Unit/integration coverage
for the consumer logic passes against real Redis (the 26 tests above). Full live-Docker end-to-end
runs with Debezium + MySQL binlog: NOT captured this session.

## Outstanding (before a full Gate 10 CLOSED verdict)
1. Live-Docker CDC failure matrix (Scenarios A–E) with Debezium + MySQL binlog.
2. `network-assertion-live.mjs` against the full real stack.
3. Commit the session changes above and let `gate10-quality.yml` (now with Redis) run green on CI.

## Final verdict
Backend CI blockers RESOLVED (0 failed, 0 skipped, 26 Redis tests executed). Gate 10 not yet
CLOSED: the live-Docker failure matrix and live network proof remain the only open evidence items.
