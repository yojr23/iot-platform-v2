# Gates 6 / 7 / 8 — completion evidence (authoritative)

**Branch:** `refraccion` · **Base HEAD (pre-commit):** `88de73aa9a77f9d19fe25239c1e919f4574902fd`
**Date:** 2026-09-10 · **Working tree:** all changes below are **uncommitted** at time of writing.
**Execution environment:** code + test authoring only. NO php / composer / docker / mysql / redis reachable this session; node + vitest present. Backend suites are **GAP — run on the operator machine** with the commands at the bottom. No PASS/FAIL was fabricated. Frontend vitest results are real (agent-executed).

Implemented with sonnet-5 subagents, one backend + one frontend agent per gate, coordinated to disjoint file sets, gate-by-gate with a review checkpoint between gates.

> **SHA note:** SEC-01 (secret history rewrite) is still OPEN and will change every SHA on this branch. Do **not** freeze a final gate SHA until the history purge + credential rotation land. Re-stamp this doc's SHA after that.

---

## Verdicts

| Gate | Verdict | Evidence quality |
|---|---|---|
| **GATE 6** — public graph boundary + sensor realtime correctness | **PASS (code + frontend tests)** | Frontend vitest green. Backend code+tests written, **runtime GAP**. MySQL EXPLAIN/index measurement (Task 6.6) **GAP** (no MySQL). Browser network-audit **GAP** (no browser harness). |
| **GATE 7** — private alerts, replay safety, durable `alert.triggered` | **PASS (code + frontend tests)** | Frontend vitest green. Backend code+tests written; consumer tests need Redis → **runtime GAP**. |
| **GATE 8** — immutable private device-status projection | **PASS (code + frontend tests)** | Frontend vitest green. Backend code+tests written, **runtime GAP**. |

**Frontend test evidence (real, `npx vitest run` in `front/`):** 26 test files, **116 tests, 0 failures**. `npm run build` succeeds. `npm run test:structure`, `test:phase4`, `test:phase5`, `test:phase7` all pass (`verify-phase5.mjs` was corrected from demanding alert polling to asserting its absence).

---

## GATE 6 — what changed

Backend (`back/`):
- `routes/api.php` — removed anonymous `/config/public`, `/dashboard/public`, `/devices/{device}/sensors`; moved `/sensors/{sensor}/latest-readings` under `auth:sanctum`. Anonymous product surface is now graph-only (`/public/graph/bootstrap`, `/public/graph/sensors/{id}/series`) plus infra `/health` + ingestion. `publicConfig`/`publicData` controller methods left in place (now route-less), noted for later removal.
- `app/Events/NewSensorReading.php` — `broadcastWith()` reduced to `reading_id, sensor_id, value(float), reading_time(UTC Z)` + envelope; removed `sensor_name/sensor_type/unit/device_name/lab_name`. Dead `handle()` removed (Laravel never self-invokes it; alert eval owned by `SensorReadingObserver`→`checkForAlert`→`AlertService`).
- `PublicGraphSeriesService` verified honest: `limit(SAMPLE_LIMIT+1)` with `SAMPLE_LIMIT=5000`, returns `truncated` + `stats.partial`. No change.
- Tests: `Gate6PublicSurfaceTest` (404/401/200 boundary matrix), `NewSensorReadingPayloadTest` (exact contract), updates to `Phase2ApiEndpointsTest` + `SensorApiControllerTest`.

Frontend (`front/`):
- `api/dashboard.js` (drop `getPublicDashboardData`), `api/config.js` (drop `getPublicConfig`, keep `getRuntimeConfig`), `api/devices.js` (`/sensors`→`/sensor-list`), `ConfigView.vue` (migrated to `getRuntimeConfig`), `DashboardView.vue` (guest makes no dashboard call; auth uses `getDashboardMetrics`).
- `stores/graphSeriesQuery.js` — window-addressable identity (`buildGraphQueryKey`, `resultForQuery(descriptor)`), consumer-keyed cancellation, removed auth latest-tail branch.
- `components/charts/graphSeriesProjection.js` (new pure `composeGraphSeries`) — history+live merged for the chart **without** hydrating the live store.
- `components/dashboard/SensorMonitorBoard.vue` — stops hydrating history into the live store; **race fix: subscribe realtime first, then load history**.
- `stores/sensorReadings.js` — hardened normalize (no invented ids), deterministic sort, cap 60, idempotent merge.
- `views/SensorDetailView.vue` — default view uses the shared live tail; historical filter kept as a local immutable result.
- `components/charts/SensorReadingChart.vue` — `partial` prop + honest partial-data warning.

Frozen ownership honored: live tail store = live only; graph query store = history only; echo/registry/composables = subscription lifecycle only; components = presentation only.

## GATE 7 — what changed

Backend:
- `app/Services/Alerts/AlertService.php` — creates the alert **and** records `alert.triggered` to the durable outbox in one `DB::transaction` (`DomainEventRecorder::record('alert.triggered','alert',$id,['alert_id'=>$id])`; real recorder is 4-arg, no `sourceEventId`).
- `app/Observers/AlertObserver.php` — removed the synchronous browser broadcast; keeps cache invalidation + `SendDangerAlertEmailJob::dispatch()->afterCommit()`.
- `app/Services/Ingestion/DomainEventBroadcastConsumer.php` — added `alert.triggered` case mirroring `alert.resolved`; dispatches `NewAlertTriggered` (already private `alerts` channel); idempotent redelivery reused.
- `resources/views/layouts/app.blade.php` — removed the legacy alert poll (`fetch('/api/alerts/active')`, 10s `setInterval`, `pusher.subscribe('alerts')`, `window.AppAlerts`). Server-rendered badges no longer live-update (no fallback added, per plan).
- Tests: `AlertTriggerTransitionTest` (one outbox row, no sync broadcast, email still queued), `DomainEventBroadcastConsumerTest` (+triggered dispatch + idempotent redelivery).

Frontend:
- `stores/alerts.js` — bounded `seenTriggeredIds`/`seenResolvedIds` ledgers; `markAlertResolved` idempotent (decrements once even after eviction); `addRealtimeAlert` dedup via ledger; `clearAuthorizedState` clears both.
- `realtime/useAlertsRealtime.js` — typed recovery buffer `{kind,payload}`; **resolved events buffered during recovery** and replayed in order (fixes snapshot resurrecting a resolved alert).
- `components/layout/AppLayout.vue` — single snapshot owner (`useAlertsRealtime`); removed direct active-alert fetch. `components/dashboard/ActiveAlertsCard.vue` — presentation-only, no fetch on mount.
- `scripts/verify-phase5.mjs` — corrected to assert the absence of alert polling.

## GATE 8 — what changed

Backend:
- `app/Services/DeviceService.php` — `changeStatus()` records the **immutable fact** `{device_id,status,is_active,changed_at}` (captured at write time; `last_communication` set from the same timestamp), not just `device_id`.
- `app/Events/DeviceStatusUpdated.php` — readonly scalar constructor (`deviceId,status,isActive,changedAt,eventSequence`), `PrivateChannel('device-status')`, `broadcastWith` = 5 keys + envelope. No longer built from a mutable model.
- `routes/channels.php` — `Broadcast::channel('device-status', fn($user)=>true)`; stale "public" comment corrected.
- `DomainEventBroadcastConsumer::broadcastDeviceStatusChanged()` — builds the event from `$outbox->payload` with `eventSequence=$outbox->id`; no `Device::find()` reload.
- Task 8.6 (email async / rate-limit release) verified — **no defect, no change**. Task 8.7 (webhook) — confirmed nothing added (stays YAGNI).
- Tests: immutable-payload + rapid OFF→ON independence in `DeviceStatusChangeTransitionTest` + `DomainEventBroadcastConsumerTest`; private-channel guest-denied/PAT-authorized in `BroadcastChannelAuthorizationTest`; envelope updates in `EventEnvelopeTest`.

Frontend:
- `stores/deviceStatuses.js` — `applyStatusEvent` with sequence guard (ignores duplicate/out-of-order), `applySnapshot` (realtime always wins), `statusFor`, `clear`.
- `realtime/useDeviceStatusRealtime.js` (new) — single private adapter, reuses `echo.js`/`channelRegistry` (no second Echo), authenticated-only, subscribe-first snapshot with buffered replay, `onResync('auth')` unsubscribe/clear, no timers.
- `AppLayout.vue` wires subscribe/unsubscribe session-wide; `DevicesView.vue` seeds snapshot + `effectiveDevice` overlay + no full reload after toggle; `DeviceDetailView.vue` overlay via `/sensor-list`; `DeviceStatusList.vue` presentation-only (removed independent `getDevices`).

---

## OPEN / GAP (operator machine)

1. **SEC-01 (P0, unchanged):** `.env.host-backup` still in history (`baead4c`, `789efbf`). Requires: rotate every leaked credential at the provider; `git filter-repo --path .env.host-backup --invert-paths` (tool not installed here); `git push --force-with-lease origin refraccion`; verify `git log --all -- .env.host-backup` empty. `.gitignore` duplicate already removed this session.
2. **Backend runtime GAP:** run the commands below on a machine with php/mysql/redis.
3. **MySQL EXPLAIN evidence (Task 6.6):** record chosen key, examined rows, filesort status, 5m/1h/24h row counts on representative data. Not runnable here.
4. **Browser/network audit (Step 8):** skipped this session by operator instruction; no Playwright/browser harness wired.

## Commands to run on the operator machine

```bash
docker compose up -d db redis back front
docker compose exec -T back php artisan migrate --force
docker compose exec -T back php artisan test
docker compose exec -T back vendor/bin/pint --test
docker compose exec -T front npm run test:unit || (cd front && npx vitest run)
docker compose exec -T front npm run build
# device-status + alert consumer tests need TEST_REDIS_HOST/PORT reachable
```
