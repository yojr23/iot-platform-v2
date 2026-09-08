# G0D — Reuse & Ownership Freeze

Implementation-start SHA: `11be23992fe590bfa4f4c5e4e2a4c6aa447dd202` (`refraccion`).
Verified against live source on 2026-09-07. Every backend/frontend class named in PLAN.md Stage G0D was read in full before this matrix was frozen.

This matrix is the authority for "who owns a domain transition" during Stages 2–10. No task may introduce a second writer/producer/cache/subscription/side-effect for a transition already owned here without stating: **Existing code reused / Existing owner retired-delegated / Compatibility window**.

## Backend ownership

| # | Existing piece (verified) | Current responsibility (source) | Action | Target owner & migration | Duplicate-path risk to avoid |
|---|---|---|---|---|---|
| B1 | `Services/Alerts/AlertService.php` (92 ln) | `triggeredRulesForReading()` rule scoping + threshold eval; `createAlertsForReading()` check-then-create (`:60-62` race, no DB unique backstop); active-count/list queries | **REUSE / EXTEND** | Stays sole rule-evaluation owner. Raw consumer (Stage 3) and any alert transition service (Stage 4) must call this, never re-implement threshold logic. Add DB unique `(sensor_reading_id, alert_rule_id)` in Stage 2 to close the race. | New listener re-scoping rules |
| B2 | `Observers/SensorReadingObserver.php` (36 ln) | `created()` → `$reading->checkForAlert()` (delegates to AlertService) + logging only | **MIGRATE / THIN** | When Stage 3 raw consumer owns reading→alert evaluation, observer must NOT call `checkForAlert` in parallel. Keep as logging/compat only, with removal note. | Observer + consumer both evaluating one reading |
| B3 | `Observers/AlertObserver.php` (35 ln) + `Services/Notifications/NotificationService.php` (97 ln) + `Models/Alert.php::sendDangerAlertEmail()` (SMTP in model, `:100-126`) | `created()` → cache-clear + `broadcastNewAlert` (sync `event(NewAlertTriggered)`) + `notifyDangerAlertByEmail` (sync `Mail::send` on request path). `updated()` → cache-clear on resolve. | **MIGRATE / SPLIT** | Broadcast + email move behind durable `iot.domain-events` consumers (Stages 4/8). Preserve NotificationService severity/rate-limit policy (`:46-67`); move SMTP transport out of `Alert.php`. Observer thinned to cache-invalidation compat only. | Old observer broadcast + new consumer broadcast; sync email + async email both firing |
| B4 | API `AlertController` (`resolve` `:60`, `resolveAll` `:76-87`) + Blade alert controllers | `resolve()` uses `$alert->update()` (fires observer). `resolveAll()` uses `Alert::active()->update()` mass update — **bypasses AlertObserver**, emits no per-alert event (audit RC2). | **UNIFY COMMAND PATHS** | Stage 4: one alert transition owner sets `resolved`+`resolved_at`+outbox+`alert.resolved` per alert in bounded chunks. API+Blade single/bulk both route through it. | Mass update leaving events unemitted |
| B5 | `Services/DeviceService.php` (26 ln, only `createDevice`) + `Api/DeviceApiController::updateStatus` (`:245-299`) + Blade device controllers | `updateStatus` mutates `status`+`is_active` directly, writes NO status log, dispatches NO `DeviceStatusUpdated` (audit §8/§9). `DeviceService` doesn't even have an update method yet. | **EXPAND + CENTRALIZE** | Stage 4/8: add `DeviceService::changeStatus()` owning device mutation + status log + `device.status.changed` emission once. API+Blade converge on it. | Two status-write paths; missing status-log/event |
| B6 | `Services/Ingestion/RawSensorEventPublisher.php` (49 ln) + `Api/IngestionController.php` (`:32-44`) | Direct `XADD iot.raw-events` with only id/node/topic/received/status — no version, no `source_event_id`, no full payload. Controller returns **201 even when publish returns false** (dual-write gap, audit §10). | **ADAPT / RETIRE per G1** | See ADR-1. Selected relay = same-tx outbox + queue relay (ADR-1). `RawSensorEventPublisher` becomes low-level XADD transport reused by the relay worker; direct call from controller retired after cutover so one receipt is never XADD'd twice. | Same receipt published twice (controller + relay) |
| B7 | Redis latest-reading cache in `Api/SensorApiController.php` (806 ln) | Latest-reading projection embedded in the fat controller | **EXTRACT / REUSE** | Stage 3/6: extract to a shared sensor-reading projection service before raw consumer writes projections. One namespace only. | Second latest-reading cache namespace |
| B8 | `Providers/EventServiceProvider.php` (39 ln) | `$listen` (Registered, DeviceCommunicationReceived) + `boot()` observes SensorReading & Alert | **EXTEND** | All new event/listener/observer wiring goes here. No `Event::listen()` scattered into controllers/services. | Scattered registration |
| B9 | `Services/Monitoring/ApiMetricsService.php` | API metrics pattern | **EXTEND PATTERN** | Stage 10 stream/realtime/consumer metrics added under `Services/Monitoring/`. No parallel `Observability/` tree. | Parallel telemetry tree |

## Frontend ownership

| # | Existing piece (verified) | Current responsibility (source) | Action | Target owner & migration | Duplicate-path risk |
|---|---|---|---|---|---|
| F1 | `front/src/realtime/echo.js` (89 ln) | Singleton `getEcho()`; **`enabledTransports:['ws','wss']` set ONLY on custom-host path (`:53`)** — hosted-cluster path lacks it (audit §12). Auth header built once from token at config time. | **EXTEND** | Stays sole Echo owner. Stage 5: set enabledTransports on ALL configs; rebuild auth on credential change; build channel registry on top. | Second Echo/Pusher connection |
| F2 | `front/src/realtime/useAlertsRealtime.js` (173 ln) + `front/src/stores/alerts.js` (239 ln) | Composable: `seenAlertIds` Set dedup (`:19,111-117`); `leaveChannel` full teardown (`:141`, no ref-count); **literal `mode:'polling'` fallback strings on disconnect/unavailable/error (`:56-70,97,150`)**. Store: `addRealtimeAlert` `wasKnown` scan dedup (`:204-205`) — a SECOND independent dedup path. | **MIGRATE / CONSOLIDATE** | Stage 5/7: dedup consolidates into the store (one projection owner). Transport stops owning domain dedup. Delete `mode:'polling'` semantics. Ref-count subscriptions. Do NOT create a 2nd realtime alert store. | Two dedup paths surviving; hybrid polling copy |
| F3 | `useSensorRealtime.js` (98 ln) | `leaveChannel` full teardown (`:82`); **declares `isConnected=true` synchronously right after `channel.listen` (`:73-77`)** — false connected state (audit RC5). | **MIGRATE** | Stage 5: connected derived from transport/subscription ack; ref-counted teardown. | Per-listener leave killing shared channel |
| F4 | `SensorMonitorBoard.vue` (reading merge / MAX_POINTS / history) | Monitor board with `pollTimer`/`startPolling`/`stopPolling` (`:525-544`), `refreshMonitor` (`:355-367`), 60-pt cap, layout, saved prefs | **MOVE / SHARE** | Stage 6: shared sensor-readings Pinia projection built by MOVING existing merge/history/bound semantics. KEEP add/remove/move/layout/prefs/chart composition. Delete the 3 poll functions in the cutover. | Rewriting monitor behavior from scratch |
| F5 | `AlertRuleModal.vue` + inline modals in `DevicesView.vue`, `SensorsView.vue` | 3 separate modal impls, no dialog role/focus trap/Escape (M05) | **GENERALIZE** | Stage 1: one `BaseModal.vue` (role/aria/focus-trap/Escape/return-focus once); migrate all three. Keep sizing CSS. | Focus trap implemented 3× |
| F6 | `SensorChart.vue` + `SensorReadingsChart.vue` | Duplicated chart config | **DRY BEFORE REALTIME** | Stage 6.0: shared chart component/helpers before event-driven changes diverge. Preserve visuals first. | Divergent chart configs |
| F7 | `components/base/*`, `utils/formatters.js` | Base controls + formatters | **REUSE FIRST** | Search before any new formatter/validation/button/loading/pagination helper. | Duplicate helpers |

## Polling inventory (delete only in cutover stages, after recovery proven — audit §7)

| Timer | Location | Stage that deletes | NOT polling (keep) |
|---|---|---|---|
| 10s active-alerts GET | `AppLayout.vue:51` | Stage 7 | — |
| 5s active-alerts GET | `ActiveAlertsCard.vue:66` | Stage 7 | — |
| ~2s per-monitor latest-reading GET | `SensorMonitorBoard.vue:525-544,536,355-367` | Stage 6 | — |
| `mode:'polling'` fallback copy | `useAlertsRealtime.js`, `alerts.js:79` | Stage 5/7 | — |
| 350ms layout-save debounce | `SensorMonitorBoard.vue:495` | keep | ✓ user-action debounce |
| 9s toast dismiss | `AlertToast.vue:88` | keep | ✓ UX timer |

**Done-when:** satisfied. Every Stage 2–10 task references the row here it migrates. No duplicated owner may survive past the stage that introduces its replacement.
