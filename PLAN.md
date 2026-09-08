# IoT Platform v2 — full migration plan (`refraccion`)

**Derives from:** `audit.md` (original audit SHA `880cffbcfc7aecb081fb378642d8693c1df64170`) plus a required application-code refresh against baseline `06d619c4c57c2cacae0c890e4dd7056097ddc79d`. Current planning-document base HEAD is `773ff7ae89bd8b88e12633e9f0e78fd7d26a5d50`; future documentation-only commits should not be mistaken for application drift. Freeze a new implementation-start SHA when coding begins. The audit is the source of truth for *why* and *what's broken*, but it is not yet fully current/reproducible. This document is the ordered, gated *how* to fix every finding and reach the Definition of Done in `audit.md §23`.

**Architecture status:** candidate, not final. The preferred candidate is the full target from `audit.md §17–§19`: transactional outbox + binlog-driven CDC relay, Redis Streams as the durable event backbone, Redis Pub/Sub as ephemeral internal fan-out, a dedicated `iot.domain-events` stream, per-consumer-group DLQ, and the complete cursor/replay/snapshot recovery protocol. Because `audit.md §15a` explicitly flags CDC and full replay as complexity risks, Stage G1 below must close Architecture Decision Records before Stages 2–10 are implemented. A Laravel-queue relay may be selected only if it still has a durable discovery/wake-up mechanism for committed outbox rows; `afterCommit()` alone is not a durable relay.

**Execution boundary:** build-and-verify in an isolated environment, stage by stage. No production rollout is authorized by this plan; the cutover stages (6/7/10) delete polling only after recovery and durable delivery are proven. Never run polling and events as a permanent hybrid.

**Golden rules gating every realtime stage (`audit.md §7`, §12, §23):** NO polling · NO polling fallback · NO hybrid realtime · NO periodic state discovery · NO browser→Redis · NO synchronous RPC disguised as events · NO unversioned durable events · NO assumption of single delivery.

**Reuse/ownership rule gating every implementation stage:** consolidate existing ownership before creating new abstractions. No task may introduce a second writer, event producer, cache projection, subscription owner, side-effect path, store, or mutation owner for an existing domain transition unless it explicitly defines the compatibility window and removal/delegation of the old owner.

---

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

## Stage G0D — Reuse & Ownership Freeze before backend/realtime code

Before Stages 2–8 write code, produce/update an ownership matrix with `REUSE`, `EXTEND`, `MIGRATE`, or `RETIRE` for every critical class below. For every new class proposed in Stages 2–8, search the repository first and identify whether an existing class already owns all or part of the same responsibility.

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

**Done when:** every Stage 2–8 task states `Existing code reused`, `Existing owner retired/delegated`, and `Compatibility window`. No duplicated owner is allowed to survive beyond the stage that introduced the replacement.

---

## Stage G1 — Architecture Decision Records before implementation

Close the contradictions between `audit.md §15` and `§15a` before building the backend migration. The ADRs must define the exact scope of "no polling", choose durable publication, choose client recovery, and choose DLQ strategy. Stages 2–10 implement those decisions, not every candidate architecture.

- **G1.1 ADR-1 durable publication:** prove transactional outbox → Debezium/binlog CDC → Redis Stream with durable offsets/checkpoints, restart survival, Redis outage recovery, and logical idempotency across duplicate/redelivered events. If CDC is too heavy for the deployment, prove same-transaction outbox + Laravel queue/worker with a durable outbox scanner or scheduler. `DB::afterCommit()` may be a low-latency wake-up hint, but it is not sufficient as the only mechanism because a process can die after commit and before job dispatch.
- **G1.2 ADR-2 client recovery:** choose full cursor/replay/snapshot/watermark recovery or a smaller V1 recovery: reconnect/visibility/mobile-resume-triggered one-shot consistent snapshot + resubscribe. Both are compatible with zero polling if they are event/lifecycle-triggered commands and never periodic state discovery.
- **G1.3 ADR-3 DLQ strategy:** choose consumer-specific Redis DLQ streams, Laravel `failed_jobs`, or another explicit quarantine mechanism per selected relay. The decision must define retry limits, poison-message handling, replay tooling, retention, ownership, and observability.
- **G1.4 Decision record package:** record selected topology, Redis Pub/Sub responsibility, deployment requirements, crash matrix, operational owner, rollback path and observability minimum.

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

- **4.1** Stand up `iot.domain-events` (using the Stage G1 relay decision for the domain outbox) with independent consumer groups `browser-delivery-v1`, `email-delivery-v1`, `webhook-delivery-v1` — `audit.md §18`. Justified over raw-only because raw data can't reconstruct manual resolution/device mutations (`audit.md §8`).
- **4.2** Add the missing emissions through existing/evolved services, precisely: `AlertController.php:76–87` `resolveAll()` uses a query-builder mass `update()` that **bypasses `AlertObserver`** (fix: route API + Blade single/bulk resolution through one alert transition owner that emits per-alert `alert.resolved` in bounded transaction chunks); `DeviceStatusUpdated` **is never dispatched** — `DeviceApiController::updateStatus` mutates status without `event(...)` (fix: evolve `DeviceService` so API + Blade status changes update device, write status log, and emit `device.status.changed` once).
- **4.3** Move transition ownership from observers to explicit services (`audit.md §9`, §15): preserve `AlertService` as the rule-evaluation owner, evolve or add an alert transition service only for lifecycle transitions, and thin `SensorReadingObserver` / `AlertObserver` so they do not compete with new listeners. Observers keep only well-defined compatibility/lifecycle behavior, never sole coverage for bulk writes; broadcasting becomes a durable async consumer, not `ShouldBroadcastNow` on the request path.
- **4.4 Redis Pub/Sub internal fan-out:** include Redis Pub/Sub explicitly as the ephemeral low-latency internal fan-out layer after the durable `browser-delivery-v1` consumer and before WebSocket broadcaster instances: `iot.domain-events` → `browser-delivery-v1` durable ACK/retry → `realtime.sensor.*` / `realtime.alerts.*` / `realtime.devices.*` Pub/Sub topics → WebSocket broadcaster(s) → Pusher-compatible transport → Echo → Vue/Pinia. Pub/Sub is never source of truth, never replay storage, never retry/DLQ mechanism, and never browser-facing.

**Done when:** every mutation entrypoint (single/bulk/API-device) emits exactly the intended durable fact under local transaction guarantees; external effects isolated.

---

## Stage 5 — Echo ownership + selected recovery protocol (audit P5 / TASK-007)

Recovery precedes any timer deletion.

- **5.1** Reference-count subscriptions: both composables call full-channel-teardown `leaveChannel` (`useAlertsRealtime.js:141`, `useSensorRealtime.js:82`) instead of per-listener removal; leave a channel only when its last consumer releases it.
- **5.2** Derive connected state from transport/subscription acks — `useSensorRealtime.js:73–77` declares connected synchronously after registering a handler. Set `enabledTransports:['ws','wss']` on **all** configs (today only the custom-host path sets it). Recreate auth headers on credential change; one Echo instance per tab.
- **5.3 Selected recovery protocol** (`audit.md §12`): implement ADR-2. If full recovery is selected, use confirm-subscription-then-buffer before the snapshot boundary; one-shot authenticated resume command with scoped cursor (202 + connection-scoped private channel, **a command, not a periodic query**); cursor-retained → replay after it to a recovery-complete watermark, dedup by ID + aggregate version; cursor-expired → pre-snapshot cursor + consistent DB projection + catch-up; bound all buffers/replay/snapshot. If the smaller V1 recovery is selected, reconnect/visibility/mobile-resume triggers one bounded authenticated resume command that returns or pushes a consistent authorized snapshot, then resubscribes and merges buffered live events. In both paths, recovery is lifecycle-triggered, bounded and never a periodic GET loop.

**Done when:** two monitors same sensor, remove one → other keeps updating; no event lost between initial history and subscription; auth switch clears inaccessible state; unauthorized cursor/channel rejected; suspend/resume + reconnect recover with zero GET loop.

---

## Stage 6 — Sensor realtime cutover, delete sensor polling (audit P6 / TASK-008)

- Wire all selected monitors to `sensor.reading.created` via the event adapter → shared sensor-readings Pinia projection → Chart.js append; dedup shared history load; bounded ordered samples (60-point cap). Build the projection by moving/reusing current `SensorMonitorBoard.vue` merge/history/MAX_POINTS behavior, not by reimplementing it from scratch.
- **Delete in the same cutover:** `SensorMonitorBoard.vue` `pollTimer`, `startPolling`/`stopPolling`, `refreshVisibleMonitors` (`:525–544`, `:536`), `refreshMonitor` (`:355–367`), and the polling-only prop. Removes 30×monitor_count req/min (`audit.md §7`).
- **Keep in the same cutover:** monitor add/remove/move, current layout, preference persistence/restoration, chart composition, and user-action debounce.
- Resolves **M03/M07** with real data now flowing (evaluate first via Stage 0 harness).

**Done when:** 1/3/5 charts with duplicate/out-of-order events + 60-point cap hold; 390→320→768→390 resize clean; **the Stage 0 no-polling network assertion shows zero periodic latest-readings.**

---

## Stage 7 — Alert realtime cutover, delete both alert timers (audit P7 / TASK-009)

- Wire `alert.triggered` / `alert.resolved` → alert adapter → existing `alerts.js` Pinia store → badge/toast/sound/active-alert UI. Apply projection idempotency in the store or one alert projection helper; resolved events never re-enter the active list; separate live count from bounded rows. Do not create a second realtime alert store.
- **Delete:** `AppLayout.vue:51` 10s timer, `ActiveAlertsCard.vue:66` 5s timer, and all fallback/polling-mode copy. Reconcile **both** dedup mechanisms — `seenAlertIds` in `useAlertsRealtime.js:19` and the `wasKnown` scan in `alerts.js:204`; neither covers the other.
- Keep the `AlertToast.vue:88` 9s dismiss + sound preference (NOT polling — `audit.md §7`).

**Done when:** create / duplicate / resolve-twice / bulk-resolve / another-session / reconnect all agree; **the Stage 0 no-polling network assertion shows zero periodic active-alert requests.**

---

## Stage 8 — Device projections + external effects (audit P8 / TASK-006, TASK-010)

- **8.1** Finish device live projection (`device.status.changed` → dashboard/list/detail/status indicators), event-driven, no periodic status refresh.
- **8.2 Email off the sync path:** `NotificationService.php:18` → `Alert.php:116` calls `Mail::send` in-process inside the reading→observer chain. Move behind the `email-delivery-v1` consumer/queued worker; fix the rate-limit-before-send gap that can suppress a legitimate retry (`audit.md §13`).
- **8.3 Webhook capability (mandatory, disabled by default):** build `webhook-delivery-v1` even if there are no configured destinations yet. Add `WebhookSubscription` / `WebhookDelivery`, HMAC over exact body + timestamp/key-id, timeout + bounded backoff, `(event_id, destination)` delivery ledger, SSRF/redirect validation, selected DLQ strategy, manual replay/disable controls, and default `enabled=false` / `destinations=[]`. This satisfies the explicit webhook requirement without sending traffic until a real destination is configured.

**Done when:** an unavailable external destination never delays ingestion or browser broadcast; idle delayed retries actually wake; another session sees resolve/status without refresh.

---

## Stage 9 — Mobile hardening (audit P9 / TASK-011)

Fix only **reproduced** defects from the Stage 0/1 evidence — M02 residuals under long text, M03 monitor-card header, M06 measured touch targets that actually fail 44×44, M07 chart resize under dense data, M08 route-splitting **only if** a measured mobile-startup number justifies it. Preserve working layouts, `.table-responsive` scrollers, table semantics. If header fixes repeat across Devices/Sensors/Catalog, extract a small `PageHeader.vue` after Playwright confirms the responsive behavior. Consolidate `SensorChart.vue` / `SensorReadingsChart.vue` into a shared chart component or shared chart helpers before adding event-driven chart fixes.

**Done when:** long/empty/dense states, touch controls, table scrollers, modals, auth, and desktop regression all pass the harness at 320/360/390/768/1440.

---

## Stage 10 — Reliability, observability, retire legacy (audit P10 / TASK-012)

- **10.1 Observability** (`audit.md §23`): Redis stream length / consumer lag / pending count / oldest-pending age / retry rate / DLQ count; domain events published-processed-latency-failed; WS connected/subscriptions/broadcast-failures/reconnects; webhook attempts/success/latency/retries/permanent-failures. Extend the existing `Services/Monitoring/ApiMetricsService.php` pattern under `Services/Monitoring/` for event/stream/realtime metrics as needed; do not introduce a parallel telemetry tree without a concrete reason.
- **10.2 Retire legacy polling client + obsolete config:** the legacy Blade routes in `back/routes/web.php` are outside the Vue timer count — gate their retirement/access explicitly before declaring the deployed platform polling-free (`audit.md §7`).
- **10.3 Prove the no-polling gate deployment-wide:** source inspection + browser network traces + transport restriction + backend relay/worker inspection. A passing build or simulated handler is insufficient (`audit.md §23`).

**Done when:** `audit.md §23` (Definition of Done) holds end to end: all polling + fallback removed, consumer pipeline complete with XACK-after-commit, pending recovery + bounded retry + selected DLQ strategy, versioned contracts, idempotent consumers, Redis Pub/Sub limited to internal fan-out, one Echo per tab, event-driven sensor/alert/device, selected event-driven recovery, async signed webhooks + async email, observability live, browser never touches Redis.

---

## Sequencing

```
SEC-1 (rotate creds) ── do immediately, blocks nothing

Stage 0 (evidence baseline) ─┬─► Stage 1 (mobile blockers) ──────────────► ship independently (frontend-only)
                             │
                             └─► Stage G1 (architecture decision)
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

Stages 1 and (G1→2→3→4→5) run in parallel after Stage 0 closes enough evidence for mobile work — no shared files. Stages 6/7/8 can parallelize once Stage 5 lands. Only 6/7/10 delete a timer/route, and only after recovery + durable delivery are proven.

## Risk gates that can change this plan (`audit.md §22, §24`)

- **Relay decision (Stage G1 / Stage 3.2):** if a MySQL-binlog→Redis relay with durable checkpoints can't be operated (open Q #6/#9 in `audit.md §24`), drop to a durable Laravel queue + outbox relay. The lean path must still include committed-outbox discovery; `afterCommit()` alone is not accepted.
- **Legacy Blade still deployed (open Q #2):** its mutation paths bypass the new transition service — inventory before Stage 10 retirement.
- **Public data intentional? (open Q #3):** decides Stage 2.3 public-vs-private per channel.
- **Rollback:** release without deleting durable data/outbox/ledgers; revert to a previous **event-capable** client, never re-enable the polling client as steady state (`audit.md §22`).
