# IoT Platform v2 — full migration plan (`refraccion`)

## Current state — read this first

- Current repository HEAD: `dfe7074` — Graphify generated artifacts only (`graphify-out/`), not an application change.
- Current application candidate: `86e4331` — frontend logger/AbortController/SensorDetailView telemetry-permission hardening (plus a small backend `SensorPolicy`/route touch-up).
- Current exact-SHA CI: PASS (Windows source gate — vitest/build/no-polling; not Mac/live).
- Windows/source verification: `<pending: SHA after 2026-09-17 front freshness/abort/regrant fixes>` (code commit for this session hasn't happened yet).
- Mac live certification: PARTIAL / OPEN.
- Gate 9: OPEN.
- Gate 10 release certification: OPEN.
- PLAN: OPEN.

Exactly one section below is authoritative for current release state: **RECONCILIATION — 2026-09-17 (Mac hardening + live-stack session)**, immediately following this block. Every other RECONCILIATION/ledger/status section further down is marked **HISTORICAL RECORD — NOT CURRENT RELEASE STATE** and is retained for audit traceability only, not as a competing source of truth.

---

**Derives from:** `audit.md` (original audit SHA `880cffbcfc7aecb081fb378642d8693c1df64170`) plus a required application-code refresh against baseline `06d619c4c57c2cacae0c890e4dd7056097ddc79d`. The planning-document base SHA for this status reconciliation is `26aedcfb05ecda2044f68d7732853cdc12a85140`. Status below is sourced from the 2026-09-14 audit handoff and repository inspection; future documentation-only commits should not be mistaken for application drift. The audit is the source of truth for *why* and *what's broken*, but it is not yet fully current/reproducible. This document is the ordered, gated *how* to fix every finding and reach the Definition of Done in `audit.md §23`.

---

## RECONCILIATION — 2026-09-17 (Mac hardening + live-stack session)

Continues the 2026-09-16 session. Full reproducible evidence in
`docs/mac-certification-evidence.md`. Starting SHA `43d6f96`. Backend suite
**448 pass / 45 skip / 1721 assertions** (SQLite) and **17/17 on real MySQL 8.0**;
frontend **262 pass**, build + no-polling gate PASS.

### Now CLOSED in source (with tests)

| Item | Status | Evidence |
|------|--------|----------|
| Dependency security (backend) | CLOSED | `composer audit` 44 advisories → **0**; `laravel/framework` 12.10.2 → 12.69.2 (fixes CRLF-in-email high + signed-URL confusion). `config.platform.php=8.2` pinned so CI (PHP 8.2) can install — an earlier `--with-all-dependencies` had pulled Symfony 8 (needs PHP 8.4) and broke the backend + security CI jobs. |
| Dependency security (frontend) | CLOSED (residual accepted) | `immutable` high patched; residual `vitest`/`esbuild` moderate are dev-only (unshipped), reviewed exception. |
| Sensor telemetry authority (SEC-RT-002) | SOURCE FIXED (frontend) — Mac live matrix PENDING | WebSocket `sensor.{id}` uses `sensor_reading.view`, while REST reading actions still reach `SensorPolicy::view`, which requires `sensor.view` — so the effective REST matrix and the private-channel matrix key off different permissions (REST `latest-readings`/`readings`/`export` require `sensor.view`; the private WebSocket channel requires `sensor_reading.view`). On the frontend this divergence is now fully handled: `SensorDetailView` separates `sensor.view` (metadata, always loaded) from `sensor_reading.view` (telemetry — gates the readings request and the private subscription), with tested permission-transition coverage (grant/revoke while mounted). A metadata-only user sees a controlled "telemetry unavailable" state instead of a broken request or a stale subscription. What remains OPEN is server-side: Mac must still choose and verify ONE server authority across REST and the private channel (today the two surfaces authorize on different permissions), including live grant/revoke flows against the real backend/broadcaster. |
| Device provisioning atomicity (B1) | CLOSED | `DeviceService::createDevice` + `store()` wrap Device row + status log in one transaction. Forced-failure test. |
| Sensor + mapping atomicity (B2) | CLOSED | `SensorApiController::store` wraps `Sensor::create` + `mapSensor`; nested tx as savepoint, verified on real MySQL. |
| Device update / delete atomicity (B3/B4) | CLOSED | metadata+status PUT and log+device delete each wrapped atomically. |
| `allReadings` global bound + validation | REOPENED — MAC BACKEND | `max(1, floor(5000 / sensorCount))` gives every loaded sensor at least one row, so more than 5,000 sensors can exceed the claimed 5,000-row ceiling; the loaded sensor objects are unbounded too. Specifically: with 6,000 sensors the formula yields `max(1, floor(5000/6000))` = 1 reading per sensor, but loading 6,000 sensor objects + 6,000 readings = 12,000 rows, breaching the 5,000-row global ceiling. With 10,000 sensors: 10,000 sensor objects (unbounded) + 10,000 readings = 20,000 rows. The sensor object list itself has no bound at all. Retain input validation, but implement and verify a genuine global sensor-and-reading bound on Mac. |
| MySQL mapping concurrency (Phase D) | CLOSED | Real-MySQL harness `back/tests/concurrency/`: 100/100 iterations, COUNT(open)=1 every time (InnoDB gap locks serialize the empty-set race). |
| CI dependency-security gate (Phase R) | CLOSED | `dependency-security` job added to `gate10-quality.yml`. |

### Live-stack evidence (partial certification)

- **Ingestion HTTP tier:** valid=201 + atomic RawSensorEvent+outbox; duplicate `source_event_id`=200 idempotent (no double-insert); wrong/missing token=401; malformed=422.
- **Phase H4 poison→DLQ (live):** unmapped-node event retried 5× → `max_attempts_exceeded` → `iot.dead-letter-events` with full diagnostic, without stalling the partition (other events processed normally).
- **Audit harness bug fixed:** live no-polling script read `data.token`; real `/api/auth/login` returns `access_token` (matches SPA). Fixed.

### Windows frontend session (2026-09-17)

Source-review findings corrected the documentation for the two REOPENED items above. Frontend permission behavior hardened:

- `SensorDetailView` now explicitly separates `sensor.view` (metadata) from `sensor_reading.view` (telemetry) at the load level: metadata loads unconditionally; telemetry load + subscription are gated by `canViewTelemetry`. A metadata-only user sees sensor data, a "telemetry unavailable" warning, and no readings request or private subscription is attempted.
- Telemetry revoke guard: once telemetry access is revoked, re-granting `sensor_reading.view` does not auto-reacquire subscription or readings (user must navigate away and back). Prevents unintended re-subscription after an explicit revoke.
- Vitest regression coverage added for permission transitions while a sensor page is mounted: `sensor.view=yes + sensor_reading.view=no` → no latest-readings call, no private subscription, metadata remains usable, controlled "telemetry unavailable" state; telemetry permission revoked → clear sensor reading projection, release private channel, do not reacquire.
- Frontend performance: try/catch with structured logging added to all API calls in views; `AbortController` for sensor detail metadata load; frontend logger utility (`createLogger`) with level filtering via `VITE_LOG_LEVEL`.
- Completion gate: `vitest run` **277 pass / 0 fail**, `vite build`, `audit:no-polling:source` all PASS.

### Still OPEN (need infra beyond core stack, or repo admin)

- Full MQTT→browser vertical (MQTT broker → Python spool; Debezium CDC relay).
- Redis/Debezium restart + XAUTOCLAIM worker-takeover + broadcast crash windows.
- Live 60s no-polling browser capture (in progress; harness fixed, run pending).
- Real-device desktop/mobile QA (Phase L).
- Branch protection (Phase Q) — needs GitHub admin.

**Gate 9: OPEN. Gate 10: OPEN. PLAN: OPEN.** Correctness/authorization/perf/
dependency phases are closed with tests; live-infra certification is partial
(ingestion + consumer + poison→DLQ done; MQTT/CDC/broadcaster/browser pending).

---

## RECONCILIATION — 2026-09-16 (Mac verification session)

**HISTORICAL RECORD — NOT CURRENT RELEASE STATE.** Superseded by the RECONCILIATION — 2026-09-17 section above. Retained for audit traceability only.

This section reconciles the plan against `refraccion` after the Mac verification/hardening
session. The validated application baseline is the tip of the fixes described below (commits
`c6daf18` → `fb1c6fc` on top of `ce5f132`). This supersedes the earlier Windows reconciliation
that was pinned to `ff8c429`; the NavBar retirement it referenced is committed at `ce5f132`.

**Headline: the two RED Gate 10 CI jobs are now GREEN, verified locally on macOS.** The backend
suite was not "13 failing" — the migration chain died before any test ran (446/452 failed at
`ce5f132`). After fixing the chain, 13 genuinely-masked failures surfaced and were all resolved.

### CI jobs — verified on this machine (SQLite + Redis 6399, PHP 8.5 locally / 8.2 on CI)

| Gate 10 job | At ce5f132 | Now |
|---|---|---|
| Backend PHP + SQLite + Redis | ❌ FAIL (446 failed) | ✅ PASS (476 tests, 0 failed, 1826 assertions) |
| Frontend unit + build + no-polling | ✅ PASS | ✅ PASS (247 tests, build clean, no-polling gate) |
| Architecture static | ✅ PASS | ✅ PASS |
| Ingestion pytest | ✅ PASS | ✅ PASS (29) |
| Responsive mocked Playwright | ❌ FAIL (33/35) | ✅ PASS (35/35) |
| MySQL `migrate:fresh` (M9, real MySQL 8.4) | not run | ✅ PASS |

Note: on local PHP 8.5 every passing test is tagged "deprecated" purely from the
`PDO::MYSQL_ATTR_SSL_CA` notice — not a failure (CI's PHP 8.2 is clean). Count real failures by
grepping `⨯`/`FAILED`.

> The preceding Mac-session notes are retained as historical evidence only; they do not certify
> the current application baseline or close any gate.

### Current release state (authoritative)

**Last Mac-certified application baseline:** `719a3f5444bbfaf2ff2cefab42f8b7dddf0542e3`.

**Current Windows/source candidate:** `700831f18dbead51712ff8e1058d4480223b0c02`
(`fix(front): hand off device credentials once`). This candidate has Windows-valid frontend/source
evidence only; it has not been certified on Mac and the documentation-only commit that records
this handoff must receive its own exact-SHA CI result.

| CI job | Status |
|---|---|
| Frontend unit/build/no-polling | PASS |
| Responsive mocked E2E | PASS |
| Backend PHP + SQLite + Redis | PASS |
| Ingestion Python | PASS |
| Architecture | PASS |

**Windows/source implementation was COMPLETE** at `700831f`. This checkout now contains the
uncommitted Windows frontend/docs correction for sensor telemetry permission UX; it needs the
completion gate and a new exact-SHA CI result after commit. Windows is not a Gate 9/Gate 10
certification environment and this does not close either gate.

**Current Windows-only evidence for `700831f`:** `npx.cmd vitest run` passed 47 files / 262
tests; `npm.cmd run build` passed; and `npm.cmd run audit:no-polling:source` passed. This is
source evidence, not live browser, backend, database, Redis, Docker, MQTT, Debezium, or WebSocket
certification.

**Mac certification remains OPEN:** device provisioning atomicity; sensor plus mapping
atomicity; mapping concurrency; the genuine allReadings sensor-and-row global bound; MQTT-to-browser E2E; Redis and
Debezium recovery; XAUTOCLAIM/reclaim; poison-to-DLQ; WebSocket disconnect/reconnect;
real-browser no-polling capture longer than 35 seconds; desktop/mobile QA; database performance;
and security/dependency audit.

**MySQL migration evidence:** `migrate:fresh` and the populated legacy upgrade path passed in a
previous Mac run. They are not open defects; rerun both against the final candidate only if
database or application migration-path code changes.

**Dependency security finding:** the latest CI evidence reports `npm audit` findings of **3
moderate and 2 high** vulnerabilities. This remains OPEN Mac/release security work: assess the
dependency paths and compatible upgrades against the final candidate; do not run
`npm audit fix --force` merely to suppress the count.

**Authorization findings requiring Mac ownership:**

1. **Authenticated graph catalog RBAC is unresolved.** `DashboardView` requests
   `/dashboard/graph-catalog` for every authenticated user, while the route presently requires
   authentication but no resource/telemetry capability and the controller enumerates all graph
   devices and sensors. Decide and test the server contract on Mac: either authenticated users
   may receive this metadata, or it requires an explicit capability. A frontend condition is
   defense in depth only and cannot close this finding.
2. **Private sensor WebSocket RBAC needs live confirmation.** Source delegates
   `private-sensor.{id}` authorization to `ResourceAccessService::canViewSensor()`, but final
   certification must exercise allowed and denied users through the real broadcaster/browser
   flow, including revocation. This is not certifiable from Windows mocks.
3. **Sensor access permissions are semantically inconsistent.** Sensor list/detail/graph-zones
   routes use `sensor.view`; the new private sensor channel uses `sensor_reading.view`; but REST
   reading actions still invoke `SensorPolicy::view` and therefore require `sensor.view`. The SPA
   route stays `sensor.view`; its Windows-only guard now treats `sensor_reading.view` as telemetry
   access and preserves metadata-only use. Define, implement, and live-test the effective REST and
   private-channel matrix (including grant/revoke) on Mac before treating either permission as a
   complete sensor-data authorization boundary.

**Gate 9: OPEN. Gate 10: OPEN. PLAN: OPEN.** Final closure is reserved for the Mac-certified
application SHA and a subsequent green documentation-only closure commit.

### Root causes fixed this session (code)

- **Migration `000008`** dropped `api_key` while the `devices_api_key_unique` index still
  referenced it — SQLite refuses; this killed `RefreshDatabase` and cascaded to the whole suite.
  Now drops the index first (idempotent guard). Portable SQLite + MySQL.
- **Migration `000003` backfill** used MySQL-only `INSERT IGNORE ... NOW()` → portable
  query-builder inserts + fail-closed collision preflight for ambiguous
  `(device_id, source, canonical external_key)` identities.
- **Migration `000002`** dropped the FK-backed unique index before any other device_id-leftmost
  index existed — MySQL error 1553 (invisible to SQLite). Now builds the replacement temporal
  index first, then drops, then renames. Verified on MySQL 8.4 **and** SQLite.
- **`CdcOutboxStreamConsumer`** had `?callable` as a promoted property type — a PHP fatal on all
  versions. Changed to `?\Closure`.
- **Timezone consistency**: `DeviceSensorMapping.valid_from/valid_until` now use Attributes that
  normalize to app tz (the default `datetime` cast dropped the `Z` offset), and
  `SensorMappingService` normalizes the lookup `$at` in both lookup methods. Temporal sensor
  resolution no longer silently skips.
- **`/config/runtime`** un-gated from admin-only `system_setting.view`; it returns only sanitized
  `{alert_sound_enabled, app_url}` and the alerts store loads it for every authenticated user.

### Test debt corrected (assertions never ran before the chain was fixed)

- alert-rules security tests → real `POST /api/alert-rules` (not `/store`).
- role-management tests → `role_code` (RBAC), superadmin actor where required.
- export tests → admin user (`sensor_reading.export` is admin-only).
- api_key leak tests → assert exact `"api_key"` key (plaintext column dropped).
- DLQ replay → order-insensitive field compare (Lua cjson tables are unordered).
- Responsive audit `adminRoutes` → only `/config*` + `/users` are admin-only; `/alert-rules`,
  `/labs`, `/sensor-types`, `/device-types` are readable by standard users per the RBAC model
  (backend gates catalog **writes** behind `system_setting.update`). RBAC redirect for the real
  admin routes verified.

### Core event-processing Docker-stack verification (M9–M12) — full evidence in `docs/GATE10_MAC_VERIFICATION_EVIDENCE_2026-09-16.md`

With the core event-processing Docker stack up (db, redis, back, front, debezium + 3 consumers; `ingestion`
excluded as it needs an external MQTT broker — events injected via `POST /api/ingestion/events`):

- **M9 upgrade path — PASS.** `php artisan migrate --force` (NOT fresh) over a DB with
  pre-existing data (1 device, 2 sensors, no mappings table) applied every new migration on
  populated tables and backfilled both sensors correctly (canonical keys, one open interval
  each, no collisions, no data loss). Real upgrade proof, complementing the `migrate:fresh` run.
- **M10 stack health — PASS.** 8 services healthy, no restart loops, `/api/health`=200. Debezium
  connected to MySQL 8.0.46, snapshot complete, streaming binlog → Redis; all CDC + domain
  streams present; consumers looping clean.
- **M11 vertical slice — PASS.** Injected a marked reading and traced all 8 stages
  HTTP→raw→normalizer(temporal mapping resolved `temperature`)→sensor_readings(42.7)→
  reading_projections(provenance)→domain outbox(published, delivered_at set)→Debezium CDC→Redis
  `iot.domain-events`. Validates the tz mapping fix on a real MySQL+Debezium stack. Device
  isolation: valid X-Device-Key → only that device's sensors; invalid/missing → 401.
- **M12 fault matrix (core) — PASS.** (1) Duplicate/at-least-once: re-POST same
  `source_event_id` → `duplicate:true`, 0 duplicate readings. (2) Consumer group health:
  pending=0, lag=0 across groups. (3) Crash+recovery: killed `outbox-cdc-consumer`, event stayed
  durable at `pending` (not lost, nothing falsely delivered); on restart, full recovery in ~8s
  (received→processed, pending→published, reading created). No loss, no manual intervention.
- **M12 remaining (OPEN):** Redis restart, Debezium restart, poison→DLQ, browser
  disconnect/reconnect, >35s real-browser no-polling capture.

### Previous reconciliation retained below for history

### Items now CLOSED in source (no further action needed)

| Item | Status | Evidence |
|---|---|---|
| Device plaintext key exposure (SEC-D6) | CLOSED | `Device::authenticate()` hash-only; `creating` event hash-only; `rotateApiKey()` hash-only; `api_key` column dropped by migration |
| Per-device IoT key returning every device's sensors | CLOSED | `iotIndex()` uses per-device `api_key_hash` SQL lookup |
| Temporal mapping prevented by old unique key | CLOSED | `2026_09_15_000002_make_device_sensor_mappings_temporal.php` removes old uniqueness |
| Historical mapping requiring current `is_active` | CLOSED | Lookup uses half-open validity window |
| RawReadingNormalizer ignoring mapping subsystem | CLOSED | Normalizer injects `SensorMappingService` and resolves via `findSensorsByExternalKeys` |
| Reading provenance detached from canonical writer | CLOSED | `ReadingProvenanceService::createReadingWithProvenance()` removed; provenance created inside DB transaction |
| Alert check-then-insert race | CLOSED | `AlertService` uses the unique constraint via `createOrFirst()` inside the outbox transaction |
| Predictable privileged seed accounts | CLOSED | SuperAdmin seeder uses random password; documented in `docs/security/` |
| API-key rotation protected only by `device.update` | CLOSED | Dedicated `rotateKey()` endpoint with `device.api_key.rotate` permission |
| E2E `isPageCorrect()` written but ignored | CLOSED | `result-policy.mjs` page identity assertions active in responsive matrix |
| Mobile Alerts/AlertRules missing `data-label` | CLOSED | `data-label` attributes added to all `<td>` elements |
| Broadcast marked/XACKed before WebSocket dispatch | CLOSED | `DomainEventBroadcastConsumer` broadcasts outside DB lock; XACK after `delivered_at` |
| `usleep` in production Ingestion code | CLOSED | Fault injection seam extracted; `faultInjectionPauseAfterPublishMs()` static for test harness only |
| Duplicate `POST /alert-rules/store` route | CLOSED | Dead route removed |
| Device API key returned by backend once | CLOSED IN BACKEND SOURCE | `DeviceApiController::store()` returns `api_key` once via `Device::pullPlaintextApiKey()`; the SPA handoff is tracked separately below |
| Silent null role_id on user create | CLOSED | `User::creating` throws `RuntimeException` when role code not found |
| Firefox download broken (detached element) | CLOSED | `SensorDetailView.vue` appends link to `document.body` before click |

### Items still OPEN (require action)

| Item | Severity | Status | Required action |
|---|---|---|---|
| Backend CI historical regression (`php artisan test`) | P0 BLOCKER | RESOLVED AT BASELINE | The current baseline `719a3f` has a green backend CI job; retain the earlier failure only as historical context. |
| Responsive E2E failing | P1 | ✅ FIXED | 35/35 mocked matrix pass; audit admin-route list corrected (commit `c6daf18`). |
| Sensor-mapping cutover/backfill | P0 | PASS PREVIOUSLY | `migrate:fresh` passed on MySQL 8.4 + SQLite; rerun on the final candidate only if database or migration-path code changes. |
| Temporal mapping concurrency | P1 | ⚠️ PARTIALLY VERIFIED | `mapSensor()` locks the identity rows; still needs a genuine concurrent-connection MySQL race test (M6). |
| `allReadings` endpoint global bound | P1 | REOPENED — MAC BACKEND | `max(1, floor(5000 / sensorCount))` breaches the 5,000-row claim above 5,000 sensors, and the sensor list is unbounded. Implement a true total ceiling on Mac and re-run MySQL evidence. |
| `api_key_hash` missing index | P2 | ✅ VERIFIED | `UNIQUE(api_key_hash)` applied cleanly in `migrate:fresh` on MySQL 8.4. |
| RBAC dual authority (`is_admin` + `role_id`) | P2 | ⚠️ CODED_NOT_VERIFIED | `is_admin` retained as migration bridge; caller migration + removal still pending (M8). |
| Live Gate 9/10 evidence | P0 | ⚠️ PARTIAL (2026-09-17) | Now done: poison→DLQ (live), ingestion HTTP tier (live), MySQL EXPLAIN, dependency audit, MySQL mapping concurrency. Still open: Redis/Debezium restart, XAUTOCLAIM, MQTT→browser, live 60s no-polling capture (harness fixed). See 2026-09-17 reconciliation. |
| MySQL upgrade/backfill path | P1 | PASS PREVIOUSLY | M9 ran `migrate --force` over a populated legacy fixture and backfilled canonical mappings without data loss; rerun on the final candidate only if database or migration-path code changes. |

### What the reviewer asked for (correction order)

> Historical correction order only. The **Current release state (authoritative)** above
> supersedes this list's older status wording.

1. ✅ **Fix backend CI** — DONE. Root cause was a migration cascade, not 13 isolated tests. 476 pass.
2. ✅ **Fix responsive E2E** — DONE. 35/35; audit's admin-route list corrected to match RBAC.
3. ✅ **Sensor-mapping cutover/backfill** — `migrate:fresh` verified on MySQL 8.4 + SQLite; fail-closed collision preflight added.
4. ⚠️ **Temporal mapping concurrency** — identity-row locking in place; genuine concurrent MySQL race test still pending (M6).
5. ⚠️ **Bound `allReadings`** — PARTIALLY FIXED / OPEN. Still `sensors × limit`, no global budget; `Carbon::parse` can 500 not 422 (M7).
6. ✅ **Index `api_key_hash`** — UNIQUE applied cleanly on MySQL 8.4.
7. ⚠️ **RBAC cleanup** — `is_admin` bridge retained; caller migration + removal pending (M8).
8. ⬜ **Live Gate 9/10 evidence** — Docker stack: WebSocket capture, fault matrix, EXPLAIN, audit (M10–M17).
9. ⬜ **Branch protection** — Requires GitHub admin access (M19).
10. 🔄 **Reconcile PLAN.md** — This section, updated to the Mac-verified baseline.

### Current test health

| Suite | Status | Count |
|---|---|---|
| Frontend unit tests | ✅ PASS | 262/262 (47 files), locally rerun on Windows 2026-09-16 for the credential lifecycle and device freshness UI |
| Frontend build | ✅ PASS | Clean |
| No-polling source gate | ✅ PASS | Zero `usleep`/polling in production |
| Architecture CI | ✅ PASS | CI verified |
| Ingestion Python | ✅ PASS | CI verified |
| Backend Laravel suite | PASS | Current baseline `719a3f` CI is green; prior Mac evidence remains supporting context. |
| Responsive E2E | ✅ PASS | 35/35 mocked responsive matrix, Mac 2026-09-16 |
| MySQL `migrate:fresh` | ✅ PASS | All migrations DONE on real MySQL 8.4 (Docker), Mac 2026-09-16 |

### Windows source corrections (committed history, 2026-09-16)

| Finding | Status | Evidence |
|---|---|---|
| Alerts and device-status lifecycle ignored RBAC capability changes | CLOSED IN SOURCE | `AppLayout.vue` derives `alert.view` and `device.view`; grant starts and revocation stops plus clears each projection |
| Alert toast mounted for every authenticated user | CLOSED IN SOURCE | `AlertToast` now requires `alert.view` |
| Alert badge fetched for users lacking `alert.view` | CLOSED IN SOURCE | `ff8c429` gates the request on `alert.view`; the legacy NavBar and its duplicate alert hydration were retired in `ce5f132` |
| Alert sound runtime config crossed `system_setting.view` boundary | CLOSED IN SOURCE | Sanitized runtime config loads for every `alert.view` user before alert subscription; `system_setting.view` remains an editing permission. |
| Sensor logout unit scenario modeled a guest resync | CLOSED IN SOURCE | Test exercises authenticated private-channel to guest public-channel transition and projection clear |
| Legacy NavBar fallback and duplicate alert snapshot owner | CLOSED IN SOURCE | All AppLayout children use LabShell; `NavBar.vue` and its separate `fetchUnresolved()` hydration are retired in `ce5f132`. |
| Connected device-status projection could remain stale after snapshot recovery failure | CLOSED IN SOURCE | `useDeviceStatusRealtime` now records recovering/stale/live freshness; failed recovery preserves buffered events, and later successful recovery restores live state. |
| Device-status freshness was not visible to operators | CLOSED IN SOURCE | `700831f` renders live/recovering/stale/disconnected state in device list/detail; focused UI and existing lifecycle tests cover recovery failure/success, buffered events, and projection clearing. |
| SPA discarded the one-time device API key | SOURCE FIXED / MAC QA OPEN | `700831f` keeps the key in a component-local ref, shows a copyable one-time modal after create/rotate, clears it on dismissal, and never places it in Pinia or browser storage. Live browser QA remains part of Mac certification. |
| Frontend API-key rotation control absent | CLOSED IN SOURCE | `700831f` calls `POST /devices/{id}/rotate-key` only from the permission-gated `device.api_key.rotate` control and presents the returned one-time key. |
| Windows frontend verification | PASS | `700831f`: `npx.cmd vitest run` 47 files / 262 tests, production build, and static no-polling gate all passed. |

---

**Architecture status:** ADR-1 selects transactional outbox + binlog-driven CDC with durable offsets as the final durable-publication topology. Redis Streams remain the durable event backbone, with `iot.domain-events` and per-consumer-group DLQ. The transitional Laravel relay/`--interval` scanner is now RETIRED — all six relay files and both compose relay services are absent (enforced by the `architecture` CI job); delivery is Debezium CDC → Redis stream → `cdc:consume-outboxes`. `afterCommit()` remains a latency hint, never a delivery guarantee.

**Execution boundary:** build-and-verify in an isolated environment, stage by stage. No production rollout is authorized by this plan; the cutover stages (6/7/10) delete polling only after recovery and durable delivery are proven. Never run polling and events as a permanent hybrid.

**Golden rules gating every realtime stage (`audit.md §7`, §12, §23):** NO polling · NO polling fallback · NO hybrid realtime · NO periodic state discovery · NO browser→Redis · NO synchronous RPC disguised as events · NO unversioned durable events · NO assumption of single delivery.

**Reuse/ownership rule gating every implementation stage:** consolidate existing ownership before creating new abstractions. No task may introduce a second writer, event producer, cache projection, subscription owner, side-effect path, store, or mutation owner for an existing domain transition unless it explicitly defines the compatibility window and removal/delegation of the old owner.

---

## Pre-Stage-6 execution corrections v1.3 — authoritative

These override any conflicting text below or in the `front_rebuild_plan/` companions. Evidence: `docs/implementation/pre-stage6-evidence.md` and `docs/implementation/reading-time-semantics.md`.

1. **`/api/iot/sensors` is credentialed, not a guest API.** `SensorApiController::iotIndex()` enforces `X-Device-Key`/`api_key` vs `config('app.api_key')` (401 on missing/wrong). Classify as non-session ingestion support; do not add a second auth scheme because `route:list` shows no middleware.
2. **`/api/health` stays anonymous** as an infrastructure liveness exception (used by `docker-compose` healthcheck). Payload is exactly `{status, app, timestamp}` — no product telemetry.
3. **Single canonical public entry = the Vue SPA at `FRONT_URL/dashboard`.** The former anonymous Blade `/dashboard` surface is retired: backend `/` and `/dashboard` redirect to the SPA, and the legacy `DashboardController`/`dashboard.blade.php` rendering surface is no longer used. Drop any "choose either entry point" language — the choice is made.
4. **Transitional public APIs are removed atomically WITH the Stage 6 replacement, never before.** `/api/dashboard/public`, `/api/config/public`, `/api/sensors/{id}/latest-readings`, `/api/devices/{device}/sensors` stay until the graph bootstrap/series vertical slice works, to avoid a guest outage. `/api/alerts/active` is Stage 7 — the "public API = graph-only" claim is false until then.
5. **No invented graph limits.** Any fixed `raw|1m` / 2,000 / 50,000 sample bounds are illustrative only; Stage 6 selects them from measured EXPLAIN cost. Pre-Stage-6 freezes only: timestamp grammar (after the timezone probe), half-open `[from,to)` semantics, DB as source of truth, bounded-query requirement, no silent truncation.
6. **Ownership split (prevents a wrong store architecture).** Live sensor projection store keyed by `sensorId` owns normalize/validate/dedup/order/latest/bounded-tail/last-observed. Historical graph query layer keyed by `authorizationScope + sensorId + from + to + aggregation` owns request identity/cancellation/source-set/statistics. `echo.js → channelRegistry.js → useSensorRealtime.js` owns channel selection/ref-count/subscribe/release/recovery. **Pinia must not own Echo channels or subscription release.**
7. **Authenticated restricted-sensor realtime** flows over an authorization-enforced private `sensor.{id}` channel (Pusher `private-sensor.{id}`), added in preflight; the public channel is gated by `PublicGraphVisibility` in Stage 6. `NewSensorReading` performs no visibility/DB lookup — the consumer decides audience.
8. **Legacy Blade sensor read surfaces are retired/redirected before any event-payload contraction** (done: `sensors/{index,show}.blade.php` deleted, routes redirect to SPA).
9. **Time semantics gate — RESOLVED.** Storage now follows a single deterministic rule (see `docs/implementation/reading-time-semantics.md`, committed `3ce38ac`): caller PHP type no longer selects semantics; `SensorReadingService::normalizeReadingTime()` is the single write-time owner. The old "classification C (mixed/ambiguous) → BLOCKED" wording is superseded. Rule still frozen: never append `Z` to offsetless timestamps to fake UTC. (MySQL probe/EXPLAIN evidence remains GAP for the run-machine.)
10. **Stage 6 begins only after `PRE-STAGE-6 GATE: PASS`** in the evidence ledger. (As of 2026-09-09 the gate is still FAIL on P0 secret hygiene + unrun backend evidence, but Stage 6/7/8 implementation was started under explicit operator direction — see the session status below.)

> **Session status, 9 September 2026 — Stage 6/7/8 started.** Full evidence: `front_rebuild_plan/STAGE_6_7_8_SESSION_EVIDENCE_2026-09-09.md`. **DONE (code+tests, uncommitted, GAP=run-machine):** Stage 6.0 server-owned graph boundary (`PublicGraphVisibility` fail-closed sole owner + `public_monitoring_enabled` column + `/api/public/graph/{bootstrap,series}` + consumer visibility gating); Stage 6.1 DRY chart owner; Stage 6.2 live projection + graph-series recovery + **sensor polling deleted** in `SensorMonitorBoard.vue`; Stage 7 **backend** (`/api/alerts/active` → `auth:sanctum`, alert events → `PrivateChannel('alerts')`); Stage 7 **frontend** (private alerts channel `{privateChannel:true}` + `AlertResolved` listener + `markAlertResolved` store method + delete `AppLayout`/`ActiveAlertsCard` timers); Stage 8.2 email off the sync path + rate-limit-release fix. Pre-gate P1 fixes also landed: sensor realtime private/public by stored token (race fixed), `NewSensorReading` fail-closed, PAT `/broadcasting/auth` test. **Test evidence (2026-09-09):** 18 test files, 69 tests, 0 failures — `npx vitest run` confirms. Test fixes applied this session: `useAlertsRealtime.test.js` updated to expect 2 `listenOnChannel` calls per subscribe (alerts + AlertResolved), `AppLayout.test.js` timer assertions updated to 0 (polling deleted), `graphSeriesQuery.test.js` abort test uses fixed timestamps to avoid key collision. **STILL OPEN:** Stage 8.1 device status backend event dispatch + frontend realtime subscription; `GET /api/config/runtime` wiring to replace `/api/config/public`; legacy Blade alert regression (expected — deferred retirement); transitional public API retirement (`/api/config/public`, `/api/dashboard/public`, `/api/sensors/{id}/latest-readings`, `/api/devices/{device}/sensors`); Stage 0 evidence baseline + no-polling assertion; Stage G0D ownership freeze; Stages 2–5 (contracts, outbox, domain events, Echo ref-counting). `PublicGraphVisibility` **is** now wired into public delivery; the earlier "not yet wired" / "expect `private-sensor.{id}`" / `00b560c` commit-review notes are resolved.

## AUTHORITATIVE CURRENT-STATUS LEDGER — 2026-09-15

**HISTORICAL RECORD — NOT CURRENT RELEASE STATE.** Despite the header, this section does not hold current authority — superseded by the RECONCILIATION — 2026-09-17 section above. Retained for audit traceability only.

**Superseded by the RECONCILIATION section above.** The reconciliation section is the authoritative current-state record as of 2026-09-15. The ledger below is retained for historical traceability only.

Status source/date: the audit handoff for this reconciliation, dated 2026-09-14, against planning base SHA `26aedcfb05ecda2044f68d7732853cdc12a85140`. This ledger supersedes older status prose where they conflict; older counts and session narratives remain below as historical audit context.

| Evidence category | Current interpretation |
|---|---|
| Code/source evidence | Repository source and authored tests document the implementation state of the role, dashboard, realtime, and ingestion changes. This is source evidence only, not proof that the runtime currently passes. |
| Current-SHA CI evidence supplied by the audit | Treat the audit-supplied current-SHA CI records as reported evidence with their recorded scope; they were not rerun in this documentation session. |
| Mocked browser evidence | Harness/mock or simulated browser results are labeled as mocked evidence and do not establish live transport, backend, or production-browser behavior. |
| New authored rows | The new auth-transition matrix rows are authored but **UNEXECUTED in this session**. Gate 9 therefore remains open/unverified. |
| External live proof | A–E live fault injection, a real WebSocket 35-second capture, MySQL EXPLAIN, dependency audit, and branch-protection verification remain external/unperformed. Gate 10 therefore remains open/unverified. |

**Gate disposition:** Gate 9 is **OPEN — unverified** pending execution of the newly authored auth-transition matrix rows. Gate 10 is **OPEN — external evidence outstanding**; no claim below or in retained history closes it.

## HISTORICAL SESSION STATUS — retained for audit history (not authoritative)

Historical runtime record retained for audit traceability: `docs/implementation/gates-6-7-8-evidence.md`. Older session notes (`front_rebuild_plan/STAGE_6_7_8_SESSION_EVIDENCE_2026-09-09.md`, `docs/implementation/pre-stage6-evidence.md`) are marked HISTORICAL/SUPERSEDED; where they conflict with the current ledger or source, the current ledger wins.

- **GATE 6 — PASS (code + frontend + backend tests).** Anonymous product APIs are graph-only (`/config/public`, `/dashboard/public`, `/devices/{id}/sensors` removed; `latest-readings` moved under `auth:sanctum`). History no longer hydrates the 60-point live store; subscribe-before-history race fixed; graph queries window-addressable with consumer-keyed cancellation; live store hardened; sensor event payload minimized; partial data surfaced. Backend php test now RUN and green (see below). Remaining GAP: MySQL EXPLAIN (6.6), browser network-audit live.
- **GATE 7 — PASS (code + frontend + backend tests).** Alert projection idempotent (bounded ledgers); recovery buffers both triggered and resolved; single snapshot owner (`useAlertsRealtime`); legacy Blade alert poll/subscribe retired; `alert.triggered` now on the durable outbox (no sync broadcast). Consumer tests now RUN against real Redis (26 CDC/domain-event tests, no longer skipped).
- **GATE 8 — PASS (code + frontend + backend tests).** Device status is an immutable fact at outbox-write time, broadcast on a private `device-status` channel with `event_sequence`; frontend projection is sequence-guarded with one private adapter shared across list/detail/dashboard; no periodic device-status GET. Email-async + rate-limit verified. Backend php test RUN and green.
- **Backend evidence (real, 2026-09-11, isolated SQLite + Redis 6399):** `php artisan test` → **299 tests, 0 failed, 0 skipped (1166 assertions)**. Root causes fixed this session: obsolete `IngestionApiTest` publisher/Redis expectations removed (controller is CDC-driven, not synchronous) + `tearDown()` hardened with `try/finally` (was cascading "already an active transaction" into 107 downstream failures); G10-PUB-08 harness artifact fixed with `forgetGuards()` (token IS revoked at logout — verified PAT 1→0); sensor-resolution N+1 fixed (constant sensor SELECT per receipt). `gate10-quality.yml` now provisions a `redis:7` service so CI runs the 26 CDC tests instead of skipping them. See `docs/implementation/gate-10-evidence.md`.
- **Frontend evidence (real):** `npx vitest run` → 36 files, **182 tests, 0 failures**; build green; `audit:no-polling:source` PASS; Playwright matrix **53/53 clean** (incl. redesigned `/metrics` at 320/360/390/768/1024/1280/1440 + dashboard desktop confirms).
- **SEC-01 — CLOSED.** Resolved; no action remaining.
- **Security hardening (P0 auth/authz) — Session 1 done (2026-09-11).** Full plan + closure table: `docs/security/SECURITY_HARDENING_PLAN.md`. Backend suite **358 tests, 0 failed, 0 skipped (1270 assertions)**. Closed with regression tests: SEC-AUTH-001 (register issues no token; verified route group — c72c411), SEC-ALERT-001 (AlertPolicy admin-only resolve/resolveAll, API+Blade — 076c350), SEC-AUTH-002 (Sanctum ability middleware enforced — e582f71), SEC-TOKEN-001 (revoke tokens on password reset + role change, API+Blade — 968c000), SEC-BOLA-001/002 + SEC-RT-001 (central `ResourceAccessService` + Sensor/Device policies + all 3 private broadcast channels delegate — 68ed5a3). **Pattern found:** parallel Blade(web)+Api controllers share domain services — every authz task must guard both surfaces (Tasks 3 & 6 each needed a Blade fix-round). **Remaining (not started):** payload minimization, SPA HttpOnly session, per-device hashed IoT creds, rate-limit keys, SMTP encryption, log redaction, bounded exports, password policy, registration policy, SMTP dest, public-window semantics, CI/branch-protection (operator), history secret scrub (operator), regression matrices.
- **HISTORICAL REPORT — GATE 9 (2026-09-11).** The retained Playwright matrix reported 53/53 clean across the DoD viewport set (320/360/390/768/1024/1280/1440), guest+authenticated, including redesigned `/metrics`. This is historical evidence only; the newly authored auth-transition rows remain unexecuted in the current session, so Gate 9 is open/unverified.
- **HISTORICAL REPORT — GATE 10 (2026-09-11).** The retained record reported backend/CDC/live-stack/browser results. This is historical evidence only and does not close Gate 10: A–E live fault injection, a real WebSocket 35-second capture, MySQL EXPLAIN, dependency audit, and branch-protection verification remain external/unperformed.
- **Private sensor history — CLOSED (2026-09-11).** New authenticated `GET /api/sensors/{sensor}/series` (reuses `PublicGraphSeriesService`, auth is the boundary — no `PublicGraphVisibility` gate) so restricted sensors return history instead of 404 on the public route. Frontend: `getPrivateGraphSeries` + `graphSeriesQuery` scope branch + `useLabWorkspace` sends `private` when authenticated. Tests: `SensorApiControllerTest` (authed 200 + guest 401 + public-route 404). Verified live in-browser.
- **Lab Blue UX fixes (2026-09-11):** 15 issues resolved across auth bugs, DOCX spec violations, broken mobile behavior, stale UI, and missing test coverage. Key changes: removed admin-only `/metrics` call from guest dashboard (Issue 1+6), added authorized device catalog fetch for authenticated users (Issue 5), wired `selectSync` to also expand widgets (Issue 2), mobile single-chart enforcement (Issue 3), disabled "Ver mini" when nothing to collapse (Issue 4), 4px last data point marker (Issue 9), custom tooltip with unit+timezone (Issue 10), WebSocket alert resolution sync (Issue 11), multi-chart invariant tests (Issue 12). Remaining: `PLAN.md` status cleanup (this entry), multi-chart test coverage (Issue 12) — written and passing.

## Traceability — every audit finding maps to a stage

| Finding (audit.md) | Root cause | Fixed in |
|---|---|---|
| M01 profile hidden on mobile nav | RC6 | Stage 1 |
| M02 admin header no wrap/stack | RC6 | Stage 1 + 9 |
| M03 monitor-card header narrow-width | RC6 | Stage 0 (evaluate) → 9 |
| M04 toast fixed 350px width | RC6 | Stage 1 |
| M05 modals lack dialog role/focus trap/Escape | RC6 | Stage 1 |
| M06 `btn-sm` touch targets | RC6 | Stage 0 (measure) → 9 |
| M07 chart resize/parent geometry | RC6 | Stage 0 (evaluate) → 6/9 |
| M08 eager router imports / bundle | RC6 | Stage 9 |
| M09 device table overflow @320px | RC6 | Stage 1 |
| RC1 split state ownership (timers/refs/Pinia) | — | Stages 6, 7 |
| RC2 incomplete domain-event coverage | — | Stages 4, 6, 7, 8 |
| RC3 non-atomic persist+publish, observer side effects | — | Stages 3, 4, 8 |
| RC4 no durable consumer ops | — | Stages 3, 10 |
| RC5 transport lifecycle ≠ projection lifecycle | — | Stage 5 |
| RC6 no behavioral mobile/a11y test | — | Stages 0, 1, 9 |
| §12a no broadcasting scaffolding (new) | RC5 | Stage 2 |
| SMTP creds in seeder (README/verified) | security | Cross-cutting (do first) |

Audit roadmap gates `G0, P1…P10` (`audit.md §19`) map to the stages below, with this plan splitting G0 into G0A/G0B/G0C/G0D and adding G1 for architecture decisions. `TASK-001…012` (`audit.md §20`) are cited per stage.

---

## Cross-cutting — do before any prod-facing config

**SEC-1 Rotate the SMTP credentials** seeded into `back/database/seeders/SystemSettingsSeeder.php` (verified 7 Sep 2026: real Gmail username + app-password-style value still present in version-controlled PHP, lines ~44/52). Move to env secrets, invalidate the leaked pair. Independent of the migration; a leaked live credential shouldn't wait behind 10 stages.

---

## Stage 0 — Close the evidence baseline (audit G0 / TASK-001)

Gate 0 is only *partially* closed (`audit.md §2a`): 38 navigate-and-screenshot runs are real, but the harness never clicks, opens a modal, injects an event, or uses long/dense fixtures — so M02/M04/M05/M06 are runtime-unproven and M03/M07 are blocked by a mock bug. Finish the baseline first; every later stage's acceptance depends on being able to *prove* a change worked.

- **0.1 G0A — Refresh current baseline:** freeze the application-code baseline SHA (`06d619c4c57c2cacae0c890e4dd7056097ddc79d` unless app code changes before work starts), rerun build/tests and the browser baseline against that exact commit/lockfile, and record dependency/version drift from the original audited SHA. Do not claim implementation readiness from the old SHA alone.
- **0.2 G0B — Make Playwright reproducible:** replace any machine-specific/global Playwright path with repository-owned dev tooling; declare the dependency in `front/package.json`; make the runner usable from a fresh clone/CI; version the full matrix that supports the claimed 38 runs instead of a partial local list.
- **0.3 G0C — Close mobile evidence:** fix `front/.audit-e2e/fixtures.mjs` field-name mismatch (`/dashboard/public` returns `{devices,sensors,alerts}` counts; the app reads `total_devices`/`active_devices`/`total_sensors`/`active_alerts`/`unresolved_alerts` and expects a device **array**). Re-run dashboard 320/390 with real data → unblocks **M03, M07** evaluation.
- **0.4** Add interaction steps to the harness: open device + alert-rule modals, inject a long/critical `AlertTriggered` to fire the toast, `getBoundingClientRect()` on `btn-sm` controls → unblocks **M04, M05, M06**.
- **0.5** Wire the `long`/`dense` fixture modes (already stubbed) into the matrix for tables + admin headers → unblocks **M02**.
- **0.6** Diagnose **M09** from the live DOM before changing layout: reproduce `devices-admin` at exactly 320×700, capture the element causing `scrollWidth=353`, inspect bounding boxes/computed styles/ancestor widths, then apply the smallest confirmed fix. Do not start by changing `.table-responsive`, headers or `.btn-group` by assumption.
- **0.7** Build the **event-injection harness** (`audit.md §21`, permanent Playwright/event test plan): inject `SensorReadingCreated`/`AlertTriggered`/`AlertResolved`/`DeviceStatusChanged` at the frontend event-adapter boundary, label results `EVENT_HANDLER_SIMULATED` (not transport-verified). This is the reusable rig every realtime stage verifies against.
- **0.8** Add the **no-polling network assertion** rig (`audit.md §7`, §21): observe network ≥3× the longest current timer interval. Today this test must detect the known polling loops and therefore fail the zero-polling assertion; that red baseline is correct. After Stages 6/7, the same rig must turn green with zero recurring latest-state REST.

**Done when:** every M01–M09 row has a real confirmed/refuted verdict; the event-injection + network-assertion rigs exist and are checked into `front/.audit-e2e/`. Fully closes G0.

## Stage G0D — Reuse & Ownership Freeze before implementation code

Before implementation code writes or moves ownership, produce/update an ownership matrix with `REUSE`, `EXTEND`, `MIGRATE`, or `RETIRE` for every critical class below. For every task in any stage that creates, replaces, generalizes, or moves ownership of an abstraction, search the repository first and identify whether an existing class already owns all or part of the same responsibility.

| Existing piece | Required action | Implementation constraint |
|---|---|---|
| `Services/Alerts/AlertService.php` | EXTEND / REUSE | Keep alert-rule evaluation here or in a clearly evolved equivalent; never reimplement threshold/rule scoping in a new listener. |
| `SensorReadingObserver` + `SensorReading::checkForAlert()` | MIGRATE / THIN | New reading listeners must not call `AlertService` in parallel with the old observer path; wrappers may remain temporarily only with a removal plan. |
| `AlertObserver` + `NotificationService` + `Alert::sendDangerAlertEmail()` | MIGRATE / SPLIT | Move broadcast/email/cache effects behind durable domain events without double broadcast/email. Preserve useful notification policy/mapping; move SMTP out of the model. |
| API + Blade alert controllers | UNIFY COMMAND PATHS | Single/bulk resolve must go through one transition owner that sets state, `resolved_at`, outbox, and `alert.resolved`. |
| `Services/DeviceService.php` + API/Blade device controllers | EXPAND + CENTRALIZE | Evolve the existing service before creating a new device transition manager; all status/log/event mutations must share one command path. |
| `Services/Ingestion/RawSensorEventPublisher.php` | ADAPT / RETIRE BY G1 | If CDC/outbox owns `XADD`, retire the direct publisher after cutover; if application relay wins, reuse/adapt its low-level behavior. Never publish the same receipt twice. |
| Redis latest-reading cache in `SensorApiController` | EXTRACT / REUSE | Extract to a shared sensor reading projection service before the raw consumer writes projections; do not create a second latest-reading cache namespace. |
| `EventServiceProvider.php` | EXTEND | Keep Laravel event/listener/observer wiring centralized here; do not scatter `Event::listen()` into controllers/services/providers. |
| `Services/Monitoring/ApiMetricsService.php` | EXTEND PATTERN | Add event/stream/realtime metrics under `Services/Monitoring/` if needed; avoid parallel `Observability/Telemetry` trees without a concrete reason. |
| `front/src/realtime/echo.js` | EXTEND | It remains the single Echo/Pusher connection owner; build connection manager/channel registry on top of it. |
| `useAlertsRealtime.js` + `front/src/stores/alerts.js` | MIGRATE / CONSOLIDATE | Move projection idempotency/dedup to the alert store or a single alert projection layer; transport must not own domain dedup. Do not create a second realtime alerts store. |
| `SensorMonitorBoard.vue` reading merge/MAX_POINTS behavior | MOVE / SHARE | Add a shared sensor-readings projection store only by reusing current merge/history/bounding semantics; keep monitor layout/preferences/chart composition. |
| `AlertRuleModal.vue` + inline device/sensor modals | GENERALIZE | Prefer a reusable accessible modal (for example `BaseModal`) so role/focus/Escape/return-focus are implemented once. |
| `SensorChart.vue` + `SensorReadingsChart.vue` | DRY BEFORE REALTIME | Extract a shared chart component or chart-data/options helpers before event-driven responsive fixes diverge. |
| Existing base components and `formatters.js` | REUSE FIRST | Search before creating new formatters, validation helpers, buttons, inputs, alerts, loading states, or pagination helpers. |

**Done when:** every task that creates, replaces, generalizes, or moves ownership of an abstraction states `Existing code reused`, `Existing owner retired/delegated`, and `Compatibility window`. No duplicated owner is allowed to survive beyond the stage that introduced the replacement.

---

## Stage G1 — Architecture Decision Records (closed)

ADR-1 defines the strict scope of NO POLLING: it includes periodic backend outbox discovery. Stages 2–10 implement the selected decisions; they do not reopen alternate relay architectures.

- **G1.1 ADR-1 durable publication — CLOSED:** selected path is transactional outbox → binlog/CDC → Redis Stream with durable offsets/checkpoints, restart survival, Redis outage recovery, and logical idempotency across duplicate/redelivered events. The transitional Laravel relay/scanner has been RETIRED (all relay files + compose services absent, enforced by the `architecture` CI job).
- **G1.2 ADR-2 client recovery:** choose full cursor/replay/snapshot/watermark recovery or a smaller V1 recovery: reconnect/visibility/mobile-resume-triggered one-shot consistent snapshot + resubscribe. Both are compatible with zero polling if they are event/lifecycle-triggered commands and never periodic state discovery.
- **G1.3 ADR-3 DLQ strategy:** choose consumer-specific Redis DLQ streams, Laravel `failed_jobs`, or another explicit quarantine mechanism per selected relay. The decision must define retry limits, poison-message handling, replay tooling, retention, ownership, and observability.
- **G1.4 ADR-4 internal realtime fan-out:** choose either `Stream consumer → Pusher-compatible broadcaster directly` or `Stream consumer → Redis Pub/Sub → N broadcaster instances`. Use Redis Pub/Sub only when there is a real fan-out or horizontal-scaling requirement; it is never source of truth, replay storage, retry/DLQ mechanism, or browser-facing transport.
- **G1.5 Decision record package:** record selected topology, deployment requirements, crash matrix, operational owner, rollback path and observability minimum.

---

## Stage 1 — Mobile blockers (audit P1 / TASK-002, frontend-only, parallelizable)

No backend dependency — ship for immediate value while Stage G1 and Stages 2–5 proceed.

- **1.1 M01 + M02 + M09:** add a mobile-visible `/profile` entry to `NavBar.vue`'s collapsed menu (remove `d-none d-md-inline` on line 48 / add to `navItems`); apply `flex-wrap`/stacking to only the page headers proven to fail under Stage 0 long/dense or 320px evidence. For M09, use the Stage 0 DOM diagnosis first; if the header is confirmed as the overflow source, fix the header and re-run `devices-admin` @320×700 to confirm `hasOverflow:false` before touching the table itself.
- **1.2 M05:** generalize the existing `AlertRuleModal.vue` into a reusable accessible modal (for example `BaseModal`) before touching three separate implementations. Add `role="dialog"` + `aria-modal`, initial focus, focus trap, Escape-to-close, return-focus once, then migrate `AlertRuleModal.vue`, inline `DevicesView.vue:28–82`, and `SensorsView.vue` to it. Keep existing sizing CSS.
- **1.3 M04:** override Bootstrap's fixed `--bs-toast-max-width:350px` (applied as `width`, not `max-width`) in `AlertToast.vue` scoped styles so it fits ≤320px.

**Done when:** the mobile portion of `audit.md §23` passes at tested widths, desktop is unregressed, and every fix is harness-confirmed.

---

## Stage 2 — Event contracts, versioning, channel security (audit P2 / TASK-003)

- **2.1 Create broadcasting scaffolding from scratch** — §12a new finding: `config/broadcasting.php`, `routes/channels.php`, and a `withBroadcasting()` call in `bootstrap/app.php` **do not exist today**. TASK-003 starts from zero.
- **2.2 Canonical versioned envelope** (`event_id, event_type, event_version:1, occurred_at, producer, aggregate_type/id, aggregate_version, correlation_id, causation_id?, bounded payload` — `audit.md §8`, event catalog). Apply to the three existing events; keep current names as backward aliases during rollout.
- **2.3 Public/private channel decision + auth routes:** `sensor.{id}`, `alerts`, `device-status` are plain public `Channel` today. Define public-safe projections vs scoped private channels; write the channel-authorization routes (they don't exist). No raw operational IDs / device keys / private user fields in browser payloads.
- **2.4 Upstream source identity:** extend the ingestion contract so the upstream producer generates `source_event_id` once and preserves it across MQTT/HTTP retries. `StoreRawIngestionEventRequest` must accept it, `raw_sensor_events` must persist it, and every outbox/domain propagation must keep event/correlation identity.
- **2.5 Idempotency keys at the schema level:** unique constraints for raw `(source, source_event_id)` and alert `(sensor_reading_id, alert_rule_id)` — the check-then-create in `AlertService.php:51–70` has a real race with no DB backstop.

**Done when:** mixed-version producers/consumers coexist; malformed payloads are rejected, additive fields are tolerated, retries preserve `source_event_id`, private events are denied to unauthorized users, and the public guest dashboard still works.

---

## Stage 3 — Durable ingestion: outbox + selected relay + raw consumer (audit P3 / TASK-004, TASK-005)

The reliability core. Implement the topology selected in Stage G1, not a mixed CDC/queue design, and apply G0D before adding any relay/consumer class.

- **3.1 Transactional outbox** (`audit.md §15`, §18): producer-generated event identity + business row + outbox row in one DB transaction. Closes the dual-write gap where `IngestionController` returns 201 even when `XADD` fails.
- **3.2 Selected relay implementation:** route typed outbox rows to `iot.raw-events`; advance durable checkpoint/cursor only after accepted delivery; survive Redis loss/restart without permanently stranding committed outbox rows. If the ADR chooses CDC, use the proven binlog connector/offset store and retire/adapt the direct `RawSensorEventPublisher` path so the same receipt is not `XADD`ed twice. If it chooses Laravel queue, include a durable outbox discovery loop in addition to any `afterCommit()` wake-up and reuse/adapt `RawSensorEventPublisher` only as low-level transport code.
- **3.3 Raw consumer lifecycle** (group `raw-process-v1`): `XREADGROUP … BLOCK` / `XACK` / `XPENDING` / `XAUTOCLAIM`, bounded concurrency, lease-based pending recovery, retry policy (batch 100, 5 attempts, bounded backoff), shutdown/backpressure, and the DLQ/quarantine mechanism selected in G1. If the full Redis Streams architecture is selected, use dedicated consumer-specific DLQ streams such as `iot.dead-letter-events`; if the lean queue path is selected, Laravel `failed_jobs` is acceptable only with replay/triage tooling and consumer-specific error metadata. This is genuinely necessary — verified: **no consumer exists anywhere** in `back/` or `ingestion_service/`.

**Done when:** DB/Redis crash matrix (before/after commit and ack) redelivers safely; receipt cannot be silently stranded; quiet-stream pending recovers; one reading + intended alert per logical event across redelivery; DLQ + replay work.

---

## Stage 4 — Domain event model + centralized transitions (audit P4 / TASK-006)

- **4.1** Stand up `iot.domain-events` (using the Stage G1 relay decision for the domain outbox) with independent consumer groups `browser-delivery-v1`, `email-delivery-v1`, and `webhook-delivery-v1` only if a real webhook destination is approved — `audit.md §18`. Justified over raw-only because raw data can't reconstruct manual resolution/device mutations (`audit.md §8`).
- **4.2** Add the missing emissions through existing/evolved services, precisely: `AlertController.php:76–87` `resolveAll()` uses a query-builder mass `update()` that **bypasses `AlertObserver`** (fix: route API + Blade single/bulk resolution through one alert transition owner that emits per-alert `alert.resolved` in bounded transaction chunks); `DeviceStatusUpdated` **is never dispatched** — `DeviceApiController::updateStatus` mutates status without `event(...)` (fix: evolve `DeviceService` so API + Blade status changes update device, write status log, and emit `device.status.changed` once).
- **4.3** Move transition ownership from observers to explicit services (`audit.md §9`, §15): preserve `AlertService` as the rule-evaluation owner, evolve or add an alert transition service only for lifecycle transitions, and thin `SensorReadingObserver` / `AlertObserver` so they do not compete with new listeners. Observers keep only well-defined compatibility/lifecycle behavior, never sole coverage for bulk writes; broadcasting becomes a durable async consumer, not `ShouldBroadcastNow` on the request path.
- **4.4 Internal fan-out implementation:** implement ADR-4. If direct delivery is selected, `browser-delivery-v1` publishes to the Pusher-compatible broadcaster directly. If Pub/Sub fan-out is selected, put Redis Pub/Sub after the durable `browser-delivery-v1` consumer and before WebSocket broadcaster instances: `iot.domain-events` → `browser-delivery-v1` durable ACK/retry → `realtime.sensor.*` / `realtime.alerts.*` / `realtime.devices.*` Pub/Sub topics → WebSocket broadcaster(s) → Pusher-compatible transport → Echo → Vue/Pinia. Pub/Sub remains ephemeral internal fan-out only.

**Done when:** every mutation entrypoint (single/bulk/API-device) emits exactly the intended durable fact under local transaction guarantees; external effects isolated.

---

## Stage 5 — Echo ownership + selected recovery protocol (audit P5 / TASK-007)

Recovery precedes any timer deletion.

- **5.1** Reference-count subscriptions: both composables call full-channel-teardown `leaveChannel` (`useAlertsRealtime.js:141`, `useSensorRealtime.js:82`) instead of per-listener removal; leave a channel only when its last consumer releases it.
- **5.2** Derive connected state from transport/subscription acks — `useSensorRealtime.js:73–77` declares connected synchronously after registering a handler. Set `enabledTransports:['ws','wss']` on **all** configs (today only the custom-host path sets it). Recreate auth headers on credential change; one Echo instance per tab.
- **5.3 Selected recovery protocol** (`audit.md §12`): implement ADR-2. If full recovery is selected, use confirm-subscription-then-buffer before the snapshot boundary; one-shot authenticated resume command with scoped cursor (202 + connection-scoped private channel, **a command, not a periodic query**); cursor-retained → replay after it to a recovery-complete watermark, dedup by ID + aggregate version; cursor-expired → pre-snapshot cursor + consistent DB projection + catch-up; bound all buffers/replay/snapshot. If the smaller V1 recovery is selected, reconnect/visibility/mobile-resume triggers one bounded authenticated resume command that returns or pushes a consistent authorized snapshot, then resubscribes and merges buffered live events. In both paths, recovery is lifecycle-triggered, bounded and never a periodic GET loop.

**Done when:** two monitors same sensor, remove one → other keeps updating; no event lost between initial history and subscription; auth switch clears inaccessible state; unauthorized cursor/channel rejected; suspend/resume + reconnect recover with zero GET loop.

---

## Stage 6 — Public graph realtime cutover, delete sensor polling (audit P6 / TASK-008)

This stage implements the Lab Blue graph foundation described in `front_rebuild_plan/FRONT_REBUILD_PLAN_ADJUSTMENTS_v1.1.md`. Begin only when the Stage 5/Gate S5-R evidence for shared Echo ownership, reference-counted release, and bounded lifecycle recovery is current.

- **6.0A Entry-point, time, query, and anonymous-surface preflight — BLOCKING:** the current public `back/routes/web.php` `/dashboard` is a Blade controller that renders metrics, active alerts, and unrestricted device/sensor data, while `front/src/views/DashboardView.vue` is the intended Lab Blue SPA surface. Select and test one production public entry point before removing the old APIs: either route anonymous `/dashboard` to the deployed Lab Blue SPA or reduce the Blade surface to the same graph-only contract. It is not acceptable to leave both public implementations or to treat the legacy Blade route as a Stage 10-only cleanup. Inventory every anonymous API at this point: delete/protect `/api/config/public` (it exposes alert configuration and the obsolete polling interval), require an ingestion credential or internal network boundary for `/api/iot/sensors`, and classify `/api/health` as a non-dashboard infrastructure liveness endpoint with an explicit deployment owner; if the external policy admits no exception, move/protect it too. The only anonymous **product-domain** operations after Stage 6 are graph bootstrap and graph series; the only anonymous product-domain stream is the approved reading channel. Before publishing a UTC contract, run a DB/application timezone probe and record how existing `sensor_readings.reading_time` values are interpreted: the current app default is `America/Bogota`, the column is a timezone-less Laravel `timestamp`, and ingestion accepts a legacy local `Y-m-d H:i:s` value. Define normalization/migration and input rejection rules first; do not relabel ambiguous historical values as UTC. Add and benchmark a composite `sensor_readings(sensor_id, reading_time, id)` index before the bounded range endpoint, because the existing table has none. The range source of truth is the indexed database; the 120-entry internal Redis latest-reading cache may never make a graph response incomplete.
- **6.0B Honest V1 graph semantics — BLOCKING:** freeze exact second-precision UTC input (`YYYY-MM-DDTHH:mm:ssZ`), half-open `[from,to)` windows, deterministic device/sensor ordering, and `default_sensor_id: null` when the bootstrap is empty. A current visibility decision is sensor-wide and history-wide: after an administrator enables a sensor, its permitted bounded stored history and future browser delivery are eligible; after disabling it, a consumer that has not broadcast a queued fact suppresses it. Do not imply a temporal visibility cutoff without a new schema/policy. V1 may show last-observed timestamp, no-data, loading/error, and browser transport state. It must not label data stale, calculate cadence/completeness/quality, render threshold bands, round by sensor precision, or call a device disconnected unless a named server-owned contract supplies those values. Min/max/mean/count for valid returned readings remain supported; threshold, quality, expected-period, and freshness policy are a separately owned future capability, not a mockup field to synthesize.
- **6.0C Compatibility before payload contraction — BLOCKING:** `NewSensorReading` currently supplies `unit` to authenticated legacy Blade sensor pages and the public Blade dashboard subscribes to all sensor channels. Before shrinking its public payload, retire/replace the public Blade dashboard route and update any retained authenticated Blade subscriber to use its already-rendered authorized sensor metadata rather than event metadata. Test those paths explicitly. Do not keep an enriched public event as a compatibility fallback, and do not break a retained authorized page silently.

- **6.0 Server-owned graph boundary:** add `sensors.public_monitoring_enabled BOOLEAN NOT NULL DEFAULT FALSE`, with no bulk-true backfill, and cast it on `Sensor`. `App\Services\Monitoring\PublicGraphVisibility` is the sole owner of `$sensor->public_monitoring_enabled === true`, exposing only `isPublic(Sensor)`, `publicSensorsQuery(): Builder`, and `requirePublic(Sensor): Sensor`. Its decision controls bootstrap inclusion, series access, and `sensor.{id}` public event production; it is never inferred from `sensor.status`, `device.status`, `device.is_active`, Device, or Lab. Replace the broad public dashboard/generic reading-device surface with a minimal public graph bootstrap and bounded UTC graph-series contract. Query public sensors first with `sensorType`/`device`, group the minimal `device(id,name)` and `sensor(id,name,unit)` DTO only after filtering, omit empty devices, and return `404` for restricted/guessed series IDs. Do not synthesize absent precision/threshold/cadence/quality metadata or create public Redis storage. `SensorReadingService`/the outbox always record the fact; `DomainEventBroadcastConsumer` evaluates visibility before dispatching `NewSensorReading`, while restricted facts are still acknowledged with no public broadcast. The current reading event's sensor/device/lab metadata must be reduced to reading identity, sensor ID, value, timestamp, and versioned envelope; public labels/unit originate in bootstrap. `NewSensorReading` must not query policy. Alerts, events, device status, preferences, global metrics, generic inventory/history, and restricted sensors are not guest data.
- **6.1 DRY chart foundation:** consolidate `SensorChart.vue` and `SensorReadingsChart.vue` into one chart owner or focused helpers. It receives a normalized graph view model, never calls HTTP/Echo, and resolves SINOA semantic tokens into Chart.js configuration.
- **6.2 Scoped sensor-reading projection:** wire selected approved graph sources through the event adapter → one live Pinia projection → shared chart owner, plus a separate historical graph query layer. The live projection is keyed by `sensorId` and owns normalization, validation, dedupe, ordering, latest value, bounded tail, and last-observed presentation. The historical query layer is keyed by authorization scope, sensor, resolved UTC window, and aggregation; it owns request identity/cancellation, source-set statistics, and immutable range hydration. `channelRegistry`/`useSensorRealtime`, not Pinia, owns Echo channel selection, ref-count, subscribe/release, recovery trigger, and public-vs-private channel switching. Clear inaccessible projection/query state on logout/scope revocation. Guest and authenticated graph areas must have visual parity for the Lab Blue graph workflow—same shell, toolbar, selectors, range controls, chart hierarchy, and empty/error treatment—while capability-only slots may use an access-required/omitted variant; do not require a shared page tree.
- **6.3 Scientific range correctness:** do not carry the legacy 60-point latest-reading cap into statistics. A five-minute range can contain 151 two-second samples; use explicit server aggregation/display decimation while retaining the valid source set for min/max/mean/count. Coverage/completeness is unavailable until an expected-cadence contract exists.
- **Delete in the same cutover:** `SensorMonitorBoard.vue` `pollTimer`, `startPolling`/`stopPolling`, `refreshVisibleMonitors`, `refreshMonitor`, the polling-only prop, and the browser interval configuration. Replace `useSensorRealtime` recovery from `latest-readings` with the graph-series adapter.
- **Keep intentionally:** chart add/remove/move/select, a public-source-only in-memory guest draft, and a user-action debounce only where it supports confirmed authenticated persistence. Do not retain background local-storage/server autosave for guests.

**Done when:** the selected production `/dashboard` entry point gives guests the Lab Blue graph UI without a second broad Blade dashboard; a deliberately public sensor is in bootstrap/series/event delivery; a restricted, guessed, and default-new sensor are absent/`404`/silent; changing operational status alone does not grant public access; an offline public sensor remains visible with its last-observed/no-data truth and no inferred device failure; durable facts exist for both visibility states; the agreed maximum (1/3/5) public chart widgets; duplicate/out-of-order/replayed readings; correct ranges and min/max/mean/count statistics without invented threshold/quality/coverage semantics; bounded recovery without a periodic GET; zero recurring graph/latest-reading/config requests; no duplicate subscription across login/logout; anonymous-route inventory passes; and the rebuilt composition passes paired guest/auth graph UI checks at 320/390/768/1440. Re-test M03/M07 only where equivalent rebuilt controls exist.

---

## Stage 7 — Authenticated alert/event cutover, delete both alert timers (audit P7 / TASK-009)

Alerts, counts, recent events, and their transport are authorized capabilities, not public-dashboard dependencies. Guest Lab Blue slots show a neutral access-required/omitted state and must not make an alert/event request, receive a count/banner identity, or subscribe to an alert channel.

- Complete `alert.triggered` and `alert.resolved` through the alert adapter → existing `alerts.js` Pinia store → authorized badge/toast/sound/active-alert UI. The store is the sole idempotency owner: resolved alerts never re-enter the active list, and rows/count/banner use the same scoped state.
- Move any authorized alert stream from the public `alerts` channel to an authorization-enforced transport before exposing it in the dashboard. Clear alert state at logout or authorization-scope change.
- Move the pre-existing public `GET /api/alerts/active` route behind `auth:sanctum` in the same Stage 7 change. Gate 6 may remove its guest caller, but the target claim that public APIs are graph-only is not true until this route is protected and guest endpoint scans confirm it.
- **Delete:** `AppLayout.vue:51` 10s timer, `ActiveAlertsCard.vue:66` 5s timer, and all fallback/polling-mode copy. Keep `AlertToast.vue:88`'s local 9s dismiss and sound preference.

**Done when:** authorized create/duplicate/resolve-twice/bulk-resolve/another-session/reconnect cases agree; guest traces contain no alert API/channel; logout clears authorized state; and the no-polling assertion shows zero periodic active-alert requests.

---

## Stage 8 — Authenticated device projections + external effects (audit P8 / TASK-006, TASK-010)

- **8.1** Finish device live projection (`device.status.changed` → authorized dashboard/list/detail/status indicators), event-driven, no periodic status refresh. Device status is not public graph data; do not add a guest `device-status` subscription merely to reproduce the mockup. Public graph metadata may identify selected-sensor context only as allowed by the graph contract.
- **8.2 Email off the sync path:** `NotificationService.php:18` → `Alert.php:116` calls `Mail::send` in-process inside the reading→observer chain. Move behind the `email-delivery-v1` consumer/queued worker; fix the rate-limit-before-send gap that can suppress a legitimate retry (`audit.md §13`).
- **8.3 Webhook extension point (transport gated):** define the domain boundary now: durable domain event → `ExternalDeliveryPort`/contract → future `webhook-delivery-v1` consumer. Do not build `WebhookSubscription`, `WebhookDelivery`, HMAC signing, retry worker, DLQ/replay UI, SSRF policy implementation, or transport code until a real destination/consumer is approved. When a destination exists, implement HMAC over exact body + timestamp/key-id, timeout + bounded backoff, `(event_id, destination)` delivery ledger, SSRF/redirect validation, selected DLQ strategy, manual replay/disable controls, and default `enabled=false` until configured.

**Done when:** an unavailable external destination never delays ingestion or browser broadcast; idle delayed retries actually wake; an authorized session sees resolve/status without refresh; and no device-status data reaches a guest graph session.

---

## Stage 9 — Lab Blue mobile and accessibility hardening (audit P9 / TASK-011)

Run the evidence harness against both rebuilt Lab Blue guest and authenticated modes. Preserve the mockup's chart-first hierarchy rather than repairing old monitor markup that has been replaced: one mounted main chart on mobile, no hidden duplicate canvases/requests, 44 px targets, logical focus order, keyboard reorder alternatives, accessible range controls, textual chart values, and no bottom-navigation obstruction. Fix only reproduced M02/M03/M06/M07/M08 defects; M03 is relevant only if an equivalent rebuilt chart-list control exists, and route splitting remains measurement-driven. Stage 9 may refine the shared chart owner from Stage 6 but must not duplicate chart configuration.

**Done when:** long/empty/dense guest and authorized states, graph controls, access-required modules, touch controls, modals, auth transitions, table scrollers, and desktop regression all pass at 320/360/390/768/1024/1280/1440.

---

## Stage 10 — Reliability, observability, retire legacy (audit P10 / TASK-012)

- **10.1 Observability** (`audit.md §23`): Redis stream length / consumer lag / pending count / oldest-pending age / retry rate / DLQ count; domain events published-processed-latency-failed; WS connected/subscriptions/broadcast-failures/reconnects; webhook attempts/success/latency/retries/permanent-failures. Extend the existing `Services/Monitoring/ApiMetricsService.php` pattern under `Services/Monitoring/` for event/stream/realtime metrics as needed; do not introduce a parallel telemetry tree without a concrete reason.
- **10.2 Retire legacy polling client + obsolete config:** the legacy Blade routes in `back/routes/web.php` are outside the Vue timer count — gate their retirement/access explicitly before declaring the deployed platform polling-free (`audit.md §7`).
- **10.3 Prove the no-polling gate deployment-wide:** source inspection + browser network traces + transport restriction + backend relay/worker inspection. A passing build or simulated handler is insufficient (`audit.md §23`).
- **10.4 Prove the public graph boundary:** backend tests and endpoint inspection must show that graph bootstrap, graph-series access, and sensor-reading broadcast production share `PublicGraphVisibility`'s exact explicit-sensor decision; the flag's default is fail-closed; restricted/guessed IDs return `404`; operational status fields never authorize disclosure; and all facts still enter the durable outbox. No broad public dashboard/inventory/history/alert/event/device-status surface remains. Guest traces show only graph REST/WebSocket activity after hydration and bounded lifecycle recovery. Authenticated traces show alerts/events/preferences clearing on logout and not leaking across scope changes.

**Done when:** `audit.md §23` (Definition of Done) holds end to end: all polling + fallback removed, consumer pipeline complete with XACK-after-commit, pending recovery + bounded retry + selected DLQ strategy, versioned contracts, idempotent consumers, ADR-4 internal fan-out implemented, one Echo per tab, event-driven sensor/alert/device, selected event-driven recovery, async email, webhook extension boundary verified and transport gated until a configured consumer exists, observability live, browser never touches Redis, and the public graph boundary has real backend/browser evidence. Timer deletion alone is not sufficient.

---

## Sequencing

```
SEC-1 (rotate creds) ── do immediately, blocks nothing

Stage 0 (evidence baseline)
   └─► Stage G0D (reuse & ownership freeze)
          ├─► Stage 1 (mobile blockers) ──────────────► ship independently (frontend-only)
          │
          └─► Stage G1 (architecture decisions)
                 └─► Stage 2 (contracts + broadcasting scaffolding)
                        └─► Stage 3 (outbox + selected relay + raw consumer)
                               └─► Stage 4 (domain events + transitions)
                                      └─► Stage 5 (Echo ownership + recovery)   ⟵ recovery MUST precede timer deletion
                                             ├─► Stage 6 (sensor cutover, delete sensor timer)
                                             ├─► Stage 7 (alert cutover, delete both alert timers)
                                             └─► Stage 8 (device + email + webhooks)
                                                    └─► Stage 9 (mobile hardening)
                                                           └─► Stage 10 (observability + retire legacy + prove no-polling)
```

Stages 1 and (G1→2→3→4→5) run in parallel after Stage 0 evidence and G0D ownership freeze — no shared files. After Stage 5, run Stage 6's server graph boundary before its projection and polling removal. Do not parallelize Stages 6 and 7 while they change shared Echo/auth lifecycle or dashboard composition. Stage 8's email/webhook boundary may proceed independently once durable-event preconditions are evidenced, but it must not alter public graph APIs or channels. Only 6/7/10 delete a timer/route, and only after recovery + durable delivery are proven.

## Risk gates that can change this plan (`audit.md §22, §24`)

- **CDC delivery gate (Stage 10):** the MySQL-binlog→Redis CDC path with durable checkpoints is the final architecture and is IN PLACE (`cdc/application.properties`, `CdcOutboxStreamConsumer`, `cdc:consume-outboxes`). The old Laravel relay's periodic discovery has been removed — no polling fallback remains. Outstanding for a full Stage 10 CLOSED: live-Docker CDC failure matrix (Scenarios A–E) + `network-assertion-live.mjs` against the real stack.
- **Internal fan-out (Stage G1 / Stage 4.4):** Redis Pub/Sub is selected only if direct `browser-delivery-v1 → Pusher-compatible broadcaster` is insufficient for scale/topology. Streams remain the durable backbone either way.
- **Webhook transport (Stage 8.3):** implement only the extension boundary until a real destination/consumer is approved; then add signed delivery, ledger, retry, SSRF validation, DLQ/replay and disabled-by-default configuration.
- **Legacy Blade still deployed (open Q #2):** its mutation paths bypass the new transition service — inventory before Stage 10 retirement.
- **Public data scope (resolved for the dashboard):** guests receive only the server-approved realtime graph path—bootstrap metadata, bounded graph series, and public sensor-reading events. Alerts, events, device status, preferences, generic inventory/history, and restricted data remain authorized-only; Stages 6–10 must enforce and prove this boundary.
- **Rollback:** release without deleting durable data/outbox/ledgers; revert to a previous **event-capable** client, never re-enable the polling client as steady state (`audit.md §22`).
