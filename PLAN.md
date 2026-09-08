# IoT Platform v2 — full migration plan (`refraccion`)

**Derives from:** `audit.md` (audited SHA `880cffbcfc7aecb081fb378642d8693c1df64170`) plus a required refresh against the current local HEAD `06d619c4c57c2cacae0c890e4dd7056097ddc79d`. The audit is the source of truth for *why* and *what's broken*, but it is not yet fully current/reproducible. This document is the ordered, gated *how* to fix every finding and reach the Definition of Done in `audit.md §23`.

**Architecture status:** candidate, not final. The preferred candidate is the full target from `audit.md §17–§19`: transactional outbox + binlog-driven CDC relay, a dedicated `iot.domain-events` stream, per-consumer-group DLQ, and the complete cursor/replay/snapshot recovery protocol. Because `audit.md §15a` explicitly flags CDC and full replay as complexity risks, Stage G1 below must close an Architecture Decision Record before Stages 2–10 are implemented. A Laravel-queue relay may be selected only if it still has a durable discovery/wake-up mechanism for committed outbox rows; `afterCommit()` alone is not a durable relay.

**Execution boundary:** build-and-verify in an isolated environment, stage by stage. No production rollout is authorized by this plan; the cutover stages (6/7/10) delete polling only after recovery and durable delivery are proven. Never run polling and events as a permanent hybrid.

**Golden rules gating every realtime stage (`audit.md §7`, §12, §23):** NO polling · NO polling fallback · NO hybrid realtime · NO periodic state discovery · NO browser→Redis · NO synchronous RPC disguised as events · NO unversioned durable events · NO assumption of single delivery.

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

Audit roadmap gates `G0, P1…P10` (`audit.md §19`) map to the stages below, with this plan splitting G0 into G0A/G0B/G0C and adding G1 for the architecture decision. `TASK-001…012` (`audit.md §20`) are cited per stage.

---

## Cross-cutting — do before any prod-facing config

**SEC-1 Rotate the SMTP credentials** seeded into `back/database/seeders/SystemSettingsSeeder.php` (verified 7 Sep 2026: real Gmail username + app-password-style value still present in version-controlled PHP, lines ~44/52). Move to env secrets, invalidate the leaked pair. Independent of the migration; a leaked live credential shouldn't wait behind 10 stages.

---

## Stage 0 — Close the evidence baseline (audit G0 / TASK-001)

Gate 0 is only *partially* closed (`audit.md §2a`): 38 navigate-and-screenshot runs are real, but the harness never clicks, opens a modal, injects an event, or uses long/dense fixtures — so M02/M04/M05/M06 are runtime-unproven and M03/M07 are blocked by a mock bug. Finish the baseline first; every later stage's acceptance depends on being able to *prove* a change worked.

- **0.1 G0A — Refresh current baseline:** freeze the current target SHA (`06d619c4c57c2cacae0c890e4dd7056097ddc79d` unless HEAD changes before work starts), rerun build/tests and the browser baseline against that exact commit/lockfile, and record dependency/version drift from the original audited SHA. Do not claim implementation readiness from the old SHA alone.
- **0.2 G0B — Make Playwright reproducible:** replace any machine-specific/global Playwright path with repository-owned dev tooling; declare the dependency in `front/package.json`; make the runner usable from a fresh clone/CI; version the full matrix that supports the claimed 38 runs instead of a partial local list.
- **0.3 G0C — Close mobile evidence:** fix `front/.audit-e2e/fixtures.mjs` field-name mismatch (`/dashboard/public` returns `{devices,sensors,alerts}` counts; the app reads `total_devices`/`active_devices`/`total_sensors`/`active_alerts`/`unresolved_alerts` and expects a device **array**). Re-run dashboard 320/390 with real data → unblocks **M03, M07** evaluation.
- **0.4** Add interaction steps to the harness: open device + alert-rule modals, inject a long/critical `AlertTriggered` to fire the toast, `getBoundingClientRect()` on `btn-sm` controls → unblocks **M04, M05, M06**.
- **0.5** Wire the `long`/`dense` fixture modes (already stubbed) into the matrix for tables + admin headers → unblocks **M02**.
- **0.6** Diagnose **M09** from the live DOM before changing layout: reproduce `devices-admin` at exactly 320×700, capture the element causing `scrollWidth=353`, inspect bounding boxes/computed styles/ancestor widths, then apply the smallest confirmed fix. Do not start by changing `.table-responsive`, headers or `.btn-group` by assumption.
- **0.7** Build the **event-injection harness** (`audit.md §21`, permanent Playwright/event test plan): inject `SensorReadingCreated`/`AlertTriggered`/`AlertResolved`/`DeviceStatusChanged` at the frontend event-adapter boundary, label results `EVENT_HANDLER_SIMULATED` (not transport-verified). This is the reusable rig every realtime stage verifies against.
- **0.8** Add the **no-polling network assertion** rig (`audit.md §7`, §21): observe network ≥3× the longest current timer interval. Today this test must detect the known polling loops and therefore fail the zero-polling assertion; that red baseline is correct. After Stages 6/7, the same rig must turn green with zero recurring latest-state REST.

**Done when:** every M01–M09 row has a real confirmed/refuted verdict; the event-injection + network-assertion rigs exist and are checked into `front/.audit-e2e/`. Fully closes G0.

## Stage G1 — Architecture Decision Record before implementation

Close the contradiction between `audit.md §15` and `§15a` before building the backend migration. The ADR must define the exact scope of "no polling", choose CDC vs a durable queue-based relay, and document why the chosen relay cannot strand a committed outbox row.

- **G1.1 Preferred candidate spike:** prove transactional outbox → Debezium/binlog CDC → Redis Stream with durable offsets/checkpoints, restart survival, Redis outage recovery, and logical idempotency across duplicate/redelivered events.
- **G1.2 Durable alternative spike:** if CDC is too heavy for the deployment, prove same-transaction outbox + Laravel queue/worker with a durable outbox scanner or scheduler. `DB::afterCommit()` may be a low-latency wake-up hint, but it is not sufficient as the only mechanism because a process can die after commit and before job dispatch.
- **G1.3 Decision record:** record selected topology, deployment requirements, crash matrix, operational owner, rollback path and observability minimum. Stages 2–10 implement this decision, not both architectures.

---

## Stage 1 — Mobile blockers (audit P1 / TASK-002, frontend-only, parallelizable)

No backend dependency — ship for immediate value while Stage G1 and Stages 2–5 proceed.

- **1.1 M01 + M02 + M09:** add a mobile-visible `/profile` entry to `NavBar.vue`'s collapsed menu (remove `d-none d-md-inline` on line 48 / add to `navItems`); apply `flex-wrap`/stacking to only the page headers proven to fail under Stage 0 long/dense or 320px evidence. For M09, use the Stage 0 DOM diagnosis first; if the header is confirmed as the overflow source, fix the header and re-run `devices-admin` @320×700 to confirm `hasOverflow:false` before touching the table itself.
- **1.2 M05:** add `role="dialog"` + `aria-modal`, initial focus, focus trap, Escape-to-close, return-focus to the three modals (`AlertRuleModal.vue`, inline in `DevicesView.vue:28–82` + `SensorsView.vue`). Keep existing sizing CSS.
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

The reliability core. Implement the topology selected in Stage G1, not a mixed CDC/queue design.

- **3.1 Transactional outbox** (`audit.md §15`, §18): producer-generated event identity + business row + outbox row in one DB transaction. Closes the dual-write gap where `IngestionController` returns 201 even when `XADD` fails.
- **3.2 Selected relay implementation:** route typed outbox rows to `iot.raw-events`; advance durable checkpoint/cursor only after accepted delivery; survive Redis loss/restart without permanently stranding committed outbox rows. If the ADR chooses CDC, use the proven binlog connector/offset store. If it chooses Laravel queue, include a durable outbox discovery loop in addition to any `afterCommit()` wake-up.
- **3.3 Raw consumer lifecycle** (group `raw-process-v1`): `XREADGROUP … BLOCK` / `XACK` / `XPENDING` / `XAUTOCLAIM`, bounded concurrency, lease-based pending recovery, retry policy (batch 100, 5 attempts, bounded backoff), shutdown/backpressure, dedicated `iot.dead-letter-events` quarantine. This is genuinely necessary — verified: **no consumer exists anywhere** in `back/` or `ingestion_service/`.

**Done when:** DB/Redis crash matrix (before/after commit and ack) redelivers safely; receipt cannot be silently stranded; quiet-stream pending recovers; one reading + intended alert per logical event across redelivery; DLQ + replay work.

---

## Stage 4 — Domain event model + centralized transitions (audit P4 / TASK-006)

- **4.1** Stand up `iot.domain-events` (using the Stage G1 relay decision for the domain outbox) with independent consumer groups `browser-delivery-v1`, `email-delivery-v1`, `webhook-delivery-v1(gated)` — `audit.md §18`. Justified over raw-only because raw data can't reconstruct manual resolution/device mutations (`audit.md §8`).
- **4.2** Add the missing emissions, precisely: `AlertController.php:76–87` `resolveAll()` uses a query-builder mass `update()` that **bypasses `AlertObserver`** (fix: emit per-alert `alert.resolved` in bounded transaction chunks); `DeviceStatusUpdated` **is never dispatched** — `DeviceApiController::updateStatus` mutates status without `event(...)` (fix: emit `device.status.changed`).
- **4.3** Move transition ownership from observers to an explicit service (`audit.md §9`, §15): observers keep only well-defined lifecycle behavior, never sole coverage for bulk writes; broadcasting becomes a durable async consumer, not `ShouldBroadcastNow` on the request path.

**Done when:** every mutation entrypoint (single/bulk/API-device) emits exactly the intended durable fact under local transaction guarantees; external effects isolated.

---

## Stage 5 — Echo ownership + full recovery protocol (audit P5 / TASK-007)

Recovery precedes any timer deletion.

- **5.1** Reference-count subscriptions: both composables call full-channel-teardown `leaveChannel` (`useAlertsRealtime.js:141`, `useSensorRealtime.js:82`) instead of per-listener removal; leave a channel only when its last consumer releases it.
- **5.2** Derive connected state from transport/subscription acks — `useSensorRealtime.js:73–77` declares connected synchronously after registering a handler. Set `enabledTransports:['ws','wss']` on **all** configs (today only the custom-host path sets it). Recreate auth headers on credential change; one Echo instance per tab.
- **5.3 Full recovery protocol** (`audit.md §12`): confirm-subscription-then-buffer before the snapshot boundary; one-shot authenticated resume command with scoped cursor (202 + connection-scoped private channel, **a command, not a periodic query**); cursor-retained → replay after it to a recovery-complete watermark, dedup by ID + aggregate version; cursor-expired → pre-snapshot cursor + consistent DB projection + catch-up; bound all buffers/replay/snapshot; handle mobile suspend/resume as lifecycle events.

**Done when:** two monitors same sensor, remove one → other keeps updating; no event lost between initial history and subscription; auth switch clears inaccessible state; unauthorized cursor/channel rejected; suspend/resume + reconnect recover with zero GET loop.

---

## Stage 6 — Sensor realtime cutover, delete sensor polling (audit P6 / TASK-008)

- Wire all selected monitors to `sensor.reading.created` via the event adapter → Pinia projection → Chart.js append; dedup shared history load; bounded ordered samples (60-point cap).
- **Delete in the same cutover:** `SensorMonitorBoard.vue` `pollTimer`, `startPolling`/`stopPolling`, `refreshVisibleMonitors` (`:525–544`, `:536`), `refreshMonitor` (`:355–367`), and the polling-only prop. Removes 30×monitor_count req/min (`audit.md §7`).
- Resolves **M03/M07** with real data now flowing (evaluate first via Stage 0 harness).

**Done when:** 1/3/5 charts with duplicate/out-of-order events + 60-point cap hold; 390→320→768→390 resize clean; **the Stage 0 no-polling network assertion shows zero periodic latest-readings.**

---

## Stage 7 — Alert realtime cutover, delete both alert timers (audit P7 / TASK-009)

- Wire `alert.triggered` / `alert.resolved` → alert adapter → Pinia → badge/toast/sound/active-alert UI. Apply resolution idempotently; resolved events never re-enter the active list; separate live count from bounded rows.
- **Delete:** `AppLayout.vue:51` 10s timer, `ActiveAlertsCard.vue:66` 5s timer, and all fallback/polling-mode copy. Reconcile **both** dedup mechanisms — `seenAlertIds` in `useAlertsRealtime.js:19` and the `wasKnown` scan in `alerts.js:204`; neither covers the other.
- Keep the `AlertToast.vue:88` 9s dismiss + sound preference (NOT polling — `audit.md §7`).

**Done when:** create / duplicate / resolve-twice / bulk-resolve / another-session / reconnect all agree; **the Stage 0 no-polling network assertion shows zero periodic active-alert requests.**

---

## Stage 8 — Device projections + external effects (audit P8 / TASK-006, TASK-010)

- **8.1** Finish device live projection (`device.status.changed` → dashboard/list/detail/status indicators), event-driven, no periodic status refresh.
- **8.2 Email off the sync path:** `NotificationService.php:18` → `Alert.php:116` calls `Mail::send` in-process inside the reading→observer chain. Move behind the `email-delivery-v1` consumer/queued worker; fix the rate-limit-before-send gap that can suppress a legitimate retry (`audit.md §13`).
- **8.3 Webhooks (gated):** build the `webhook-delivery-v1` consumer only if/when a real destination is identified — HMAC over exact body + timestamp/key-id, timeout + bounded backoff, `(event_id, destination)` delivery ledger, SSRF/redirect validation, DLQ. `audit.md §13`/open-question #7 keep this disabled by default; the extension boundary is defined now, code lands when a consumer exists.

**Done when:** an unavailable external destination never delays ingestion or browser broadcast; idle delayed retries actually wake; another session sees resolve/status without refresh.

---

## Stage 9 — Mobile hardening (audit P9 / TASK-011)

Fix only **reproduced** defects from the Stage 0/1 evidence — M02 residuals under long text, M03 monitor-card header, M06 measured touch targets that actually fail 44×44, M07 chart resize under dense data, M08 route-splitting **only if** a measured mobile-startup number justifies it. Preserve working layouts, `.table-responsive` scrollers, table semantics.

**Done when:** long/empty/dense states, touch controls, table scrollers, modals, auth, and desktop regression all pass the harness at 320/360/390/768/1440.

---

## Stage 10 — Reliability, observability, retire legacy (audit P10 / TASK-012)

- **10.1 Observability** (`audit.md §23`): Redis stream length / consumer lag / pending count / oldest-pending age / retry rate / DLQ count; domain events published-processed-latency-failed; WS connected/subscriptions/broadcast-failures/reconnects; webhook attempts/success/latency/retries/permanent-failures. Proportional to scale.
- **10.2 Retire legacy polling client + obsolete config:** the legacy Blade routes in `back/routes/web.php` are outside the Vue timer count — gate their retirement/access explicitly before declaring the deployed platform polling-free (`audit.md §7`).
- **10.3 Prove the no-polling gate deployment-wide:** source inspection + browser network traces + transport restriction + backend relay/worker inspection. A passing build or simulated handler is insufficient (`audit.md §23`).

**Done when:** `audit.md §23` (Definition of Done) holds end to end: all polling + fallback removed, consumer pipeline complete with XACK-after-commit, pending recovery + bounded retry + DLQ, versioned contracts, idempotent consumers, one Echo per tab, event-driven sensor/alert/device, event-driven recovery, async signed webhooks + async email, observability live, browser never touches Redis.

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
