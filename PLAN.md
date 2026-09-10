# IoT Platform v2 — full migration plan (`refraccion`)

**Derives from:** `audit.md` (original audit SHA `880cffbcfc7aecb081fb378642d8693c1df64170`) plus a required application-code refresh against baseline `06d619c4c57c2cacae0c890e4dd7056097ddc79d`. Current planning-document base HEAD is `773ff7ae89bd8b88e12633e9f0e78fd7d26a5d50`; future documentation-only commits should not be mistaken for application drift. Freeze a new implementation-start SHA when coding begins. The audit is the source of truth for *why* and *what's broken*, but it is not yet fully current/reproducible. This document is the ordered, gated *how* to fix every finding and reach the Definition of Done in `audit.md §23`.

**Architecture status:** ADR-1 now selects transactional outbox + binlog-driven CDC with durable offsets as the final durable-publication topology. Redis Streams remain the durable event backbone, with `iot.domain-events` and per-consumer-group DLQ. The existing Laravel relay with `--interval=2` is transitional only and must retire at the final architecture gate; `afterCommit()` remains a latency hint, never a delivery guarantee.

**Execution boundary:** build-and-verify in an isolated environment, stage by stage. No production rollout is authorized by this plan; the cutover stages (6/7/10) delete polling only after recovery and durable delivery are proven. Never run polling and events as a permanent hybrid.

**Golden rules gating every realtime stage (`audit.md §7`, §12, §23):** NO polling · NO polling fallback · NO hybrid realtime · NO periodic state discovery · NO browser→Redis · NO synchronous RPC disguised as events · NO unversioned durable events · NO assumption of single delivery.

**Reuse/ownership rule gating every implementation stage:** consolidate existing ownership before creating new abstractions. No task may introduce a second writer, event producer, cache projection, subscription owner, side-effect path, store, or mutation owner for an existing domain transition unless it explicitly defines the compatibility window and removal/delegation of the old owner.

---

## Pre-Stage-6 execution corrections v1.3 — authoritative

These override any conflicting text below or in the `front_rebuild_plan/` companions. Evidence: `docs/implementation/pre-stage6-evidence.md` and `docs/implementation/reading-time-semantics.md`.

1. **`/api/iot/sensors` is credentialed, not a guest API.** `SensorApiController::iotIndex()` enforces `X-Device-Key`/`api_key` vs `config('app.api_key')` (401 on missing/wrong). Classify as non-session ingestion support; do not add a second auth scheme because `route:list` shows no middleware.
2. **`/api/health` stays anonymous** as an infrastructure liveness exception (used by `docker-compose` healthcheck). Payload is exactly `{status, app, timestamp}` — no product telemetry.
3. **Single canonical public entry = the Vue SPA at `FRONT_URL/dashboard`.** The former anonymous Blade `/dashboard` is retired (backend now redirects `/` and `/dashboard` to the SPA; `DashboardController` + `dashboard.blade.php` deleted). Drop any "choose either entry point" language — the choice is made.
4. **Transitional public APIs are removed atomically WITH the Stage 6 replacement, never before.** `/api/dashboard/public`, `/api/config/public`, `/api/sensors/{id}/latest-readings`, `/api/devices/{device}/sensors` stay until the graph bootstrap/series vertical slice works, to avoid a guest outage. `/api/alerts/active` is Stage 7 — the "public API = graph-only" claim is false until then.
5. **No invented graph limits.** Any fixed `raw|1m` / 2,000 / 50,000 sample bounds are illustrative only; Stage 6 selects them from measured EXPLAIN cost. Pre-Stage-6 freezes only: timestamp grammar (after the timezone probe), half-open `[from,to)` semantics, DB as source of truth, bounded-query requirement, no silent truncation.
6. **Ownership split (prevents a wrong store architecture).** Live sensor projection store keyed by `sensorId` owns normalize/validate/dedup/order/latest/bounded-tail/last-observed. Historical graph query layer keyed by `authorizationScope + sensorId + from + to + aggregation` owns request identity/cancellation/source-set/statistics. `echo.js → channelRegistry.js → useSensorRealtime.js` owns channel selection/ref-count/subscribe/release/recovery. **Pinia must not own Echo channels or subscription release.**
7. **Authenticated restricted-sensor realtime** flows over an authorization-enforced private `sensor.{id}` channel (Pusher `private-sensor.{id}`), added in preflight; the public channel is gated by `PublicGraphVisibility` in Stage 6. `NewSensorReading` performs no visibility/DB lookup — the consumer decides audience.
8. **Legacy Blade sensor read surfaces are retired/redirected before any event-payload contraction** (done: `sensors/{index,show}.blade.php` deleted, routes redirect to SPA).
9. **Time semantics gate — RESOLVED.** Storage now follows a single deterministic rule (see `docs/implementation/reading-time-semantics.md`, committed `3ce38ac`): caller PHP type no longer selects semantics; `SensorReadingService::normalizeReadingTime()` is the single write-time owner. The old "classification C (mixed/ambiguous) → BLOCKED" wording is superseded. Rule still frozen: never append `Z` to offsetless timestamps to fake UTC. (MySQL probe/EXPLAIN evidence remains GAP for the run-machine.)
10. **Stage 6 begins only after `PRE-STAGE-6 GATE: PASS`** in the evidence ledger. (As of 2026-09-09 the gate is still FAIL on P0 secret hygiene + unrun backend evidence, but Stage 6/7/8 implementation was started under explicit operator direction — see the session status below.)

> **Session status, 9 September 2026 — Stage 6/7/8 started.** Full evidence: `front_rebuild_plan/STAGE_6_7_8_SESSION_EVIDENCE_2026-09-09.md`. **DONE (code+tests, uncommitted, GAP=run-machine):** Stage 6.0 server-owned graph boundary (`PublicGraphVisibility` fail-closed sole owner + `public_monitoring_enabled` column + `/api/public/graph/{bootstrap,series}` + consumer visibility gating); Stage 6.1 DRY chart owner; Stage 6.2 live projection + graph-series recovery + **sensor polling deleted** in `SensorMonitorBoard.vue`; Stage 7 **backend** (`/api/alerts/active` → `auth:sanctum`, alert events → `PrivateChannel('alerts')`); Stage 7 **frontend** (private alerts channel `{privateChannel:true}` + `AlertResolved` listener + `markAlertResolved` store method + delete `AppLayout`/`ActiveAlertsCard` timers); Stage 8.2 email off the sync path + rate-limit-release fix. Pre-gate P1 fixes also landed: sensor realtime private/public by stored token (race fixed), `NewSensorReading` fail-closed, PAT `/broadcasting/auth` test. **Test evidence (2026-09-09):** 18 test files, 69 tests, 0 failures — `npx vitest run` confirms. Test fixes applied this session: `useAlertsRealtime.test.js` updated to expect 2 `listenOnChannel` calls per subscribe (alerts + AlertResolved), `AppLayout.test.js` timer assertions updated to 0 (polling deleted), `graphSeriesQuery.test.js` abort test uses fixed timestamps to avoid key collision. **STILL OPEN:** P0 `.env.host-backup` purge + credential rotation (blocked by operator push-hold); Stage 8.1 device status backend event dispatch + frontend realtime subscription; `GET /api/config/runtime` wiring to replace `/api/config/public`; legacy Blade alert regression (expected — deferred retirement); transitional public API retirement (`/api/config/public`, `/api/dashboard/public`, `/api/sensors/{id}/latest-readings`, `/api/devices/{device}/sensors`); Stage 0 evidence baseline + no-polling assertion; Stage G0D ownership freeze; Stages 2–5 (contracts, outbox, domain events, Echo ref-counting). `PublicGraphVisibility` **is** now wired into public delivery; the earlier "not yet wired" / "expect `private-sensor.{id}`" / `00b560c` commit-review notes are resolved.

## CURRENT STATUS — Gates 6/7/8 code-complete (2026-09-10)

Authoritative runtime record: `docs/implementation/gates-6-7-8-evidence.md`. Older session notes (`front_rebuild_plan/STAGE_6_7_8_SESSION_EVIDENCE_2026-09-09.md`, `docs/implementation/pre-stage6-evidence.md`) are marked HISTORICAL/SUPERSEDED; where they conflict with current source, source wins.

- **GATE 6 — PASS (code + frontend tests).** Anonymous product APIs are graph-only (`/config/public`, `/dashboard/public`, `/devices/{id}/sensors` removed; `latest-readings` moved under `auth:sanctum`). History no longer hydrates the 60-point live store; subscribe-before-history race fixed; graph queries window-addressable with consumer-keyed cancellation; live store hardened; sensor event payload minimized; partial data surfaced. GAP: backend php test, MySQL EXPLAIN (6.6), browser network-audit.
- **GATE 7 — PASS (code + frontend tests).** Alert projection idempotent (bounded ledgers); recovery buffers both triggered and resolved; single snapshot owner (`useAlertsRealtime`); legacy Blade alert poll/subscribe retired; `alert.triggered` now on the durable outbox (no sync broadcast). GAP: backend php test (consumer tests need Redis).
- **GATE 8 — PASS (code + frontend tests).** Device status is an immutable fact at outbox-write time, broadcast on a private `device-status` channel with `event_sequence`; frontend projection is sequence-guarded with one private adapter shared across list/detail/dashboard; no periodic device-status GET. Email-async + rate-limit verified (no change); webhook stays deferred. GAP: backend php test.
- **Frontend evidence (real):** `npx vitest run` → 26 files, **116 tests, 0 failures**; build green; `verify-phase{structure,4,5,7}` green.
- **SEC-01 — still OPEN (operator-only):** `.env.host-backup` in history (`baead4c`, `789efbf`); rotate credentials + `git filter-repo` purge + `--force-with-lease` push. `.gitignore` duplicate removed. Do not freeze a final gate SHA until the rewrite lands (it changes SHAs).

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

- **G1.1 ADR-1 durable publication — CLOSED:** selected path is transactional outbox → binlog/CDC → Redis Stream with durable offsets/checkpoints, restart survival, Redis outage recovery, and logical idempotency across duplicate/redelivered events. The Laravel relay/scanner is a transitional exception only; retire it before Stage 10's final architecture gate.
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

- **CDC delivery gate (Stage 10):** the MySQL-binlog→Redis relay with durable checkpoints is mandatory for the final architecture. The current Laravel relay's periodic discovery is transitional and cannot be retained as a fallback once the final no-polling gate is evaluated.
- **Internal fan-out (Stage G1 / Stage 4.4):** Redis Pub/Sub is selected only if direct `browser-delivery-v1 → Pusher-compatible broadcaster` is insufficient for scale/topology. Streams remain the durable backbone either way.
- **Webhook transport (Stage 8.3):** implement only the extension boundary until a real destination/consumer is approved; then add signed delivery, ledger, retry, SSRF validation, DLQ/replay and disabled-by-default configuration.
- **Legacy Blade still deployed (open Q #2):** its mutation paths bypass the new transition service — inventory before Stage 10 retirement.
- **Public data scope (resolved for the dashboard):** guests receive only the server-approved realtime graph path—bootstrap metadata, bounded graph series, and public sensor-reading events. Alerts, events, device status, preferences, generic inventory/history, and restricted data remain authorized-only; Stages 6–10 must enforce and prove this boundary.
- **Rollback:** release without deleting durable data/outbox/ledgers; revert to a previous **event-capable** client, never re-enable the polling client as steady state (`audit.md §22`).
