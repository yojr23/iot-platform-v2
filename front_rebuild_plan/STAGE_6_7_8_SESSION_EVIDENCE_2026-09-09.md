# Stage 6/7/8 — session evidence (2026-09-09)

**Branch:** `refraccion` · **HEAD at session start/end:** `faae3aa` (all work below is **uncommitted working-tree** — no commit, no push, per operator instruction).
**Runtime of this session:** coding + test-authoring only. NO php/composer/mysql/docker reachable; node/npm/vitest ARE present (two frontend agents executed real vitest — noted per item). Every backend suite is **GAP: run on the run-machine** with the commands listed at the bottom. No PASS/FAIL was fabricated.

Executed with sonnet-5 subagents in parallel/serialized waves, coordinated to disjoint file sets. Skills (from `opencode-skills-plugins`, surfed via `graphify-out/SKILLS_GRAPH.json`) were read and applied per agent: `superpowers/{test-driven-development,executing-plans,verification-before-completion,systematic-debugging,dispatching-parallel-agents,requesting/receiving-code-review,brainstorming}`, `build-web-data-visualization/{dashboards-and-real-time,data-visualization,react-and-nextjs,statistical-and-uncertainty,accessibility-and-inclusive,testing-data-visualizations,visualization-strategy,canvas2d,typescript-engineering}`, `build-web-apps/{frontend-app-builder,frontend-testing-debugging,react-best-practices,supabase-best-practices}`, `codex-security/{threat-model,security-scan,security-diff-scan,propose-security-hardening,validation,fix-finding}`, `coderabbit/coderabbit-review`, `cloudflare/web-perf`, `temporal/temporal-developer`, `twilio-developer-kit/twilio-webhook-architecture`, `vercel/investigation-mode`.

---

## DONE (code + tests written this session)

### Pre-gate P1 blockers (from the pre-Stage-6 review)
- **Sensor realtime auth-transition race — FIXED.** `front/src/realtime/useSensorRealtime.js` now selects public vs private channel by `Boolean(getStoredToken())` (the same credential source `echo.js` uses for `/broadcasting/auth`), not `authStore.isAuthenticated` (`token && user`), which was `false` in the login race window. +5 transition tests (guest→login, logout, 401) in `useSensorRealtime.test.js`. **Front vitest ran green (agent-executed): 17 realtime + 30 unit + build.**
- **`NewSensorReading` fail-closed — FIXED.** Constructor audience defaults flipped to `includePublicChannel=false, includePrivateChannel=false`; bare construction broadcasts on no channel. Sole production dispatcher (`DomainEventBroadcastConsumer`) passes audience explicitly. `EventEnvelopeTest` + `DomainEventBroadcastConsumerTest` updated.
- **PAT `/broadcasting/auth` proof — ADDED.** `BroadcastChannelAuthorizationTest` now exercises a real Sanctum PAT via `withToken()->postJson('/api/broadcasting/auth', private-sensor.{id})`. Confirmed `bootstrap/app.php` `withBroadcasting(... middleware: ['auth:sanctum'])` — Bearer PAT authenticates.
- **Reading-time semantics — RESOLVED** (already committed `3ce38ac` + `docs/implementation/reading-time-semantics.md`). Stage 6.0 series query reuses `SensorReadingService::normalizeReadingTime()` so incoming UTC `from/to` are converted to the stored Bogotá wall-clock convention (no 5h drift). This supersedes the old "classification C / BLOCKED" text.

### Stage 6.0 — server-owned public graph boundary  ✅ code
- NEW `back/app/Services/Monitoring/PublicGraphVisibility.php` — **sole owner**, fail-closed: `isPublic()`, `publicSensorsQuery()`, `requirePublic()` (404). Never infers from `sensor.status`/`device.status`/`is_active`.
- NEW `back/app/Services/Monitoring/PublicGraphSeriesService.php` — bounded half-open `[from,to)` UTC query, `min/max/mean/count` over the full valid source set (NOT capped at 60/120). `ponytail:` `SAMPLE_LIMIT=5000` illustrative ceiling pending measured EXPLAIN.
- NEW `back/app/Http/Controllers/Api/PublicGraphController.php` — `bootstrap()` + `series()`.
- NEW migration `..._add_public_monitoring_enabled_to_sensors_table.php` — `BOOLEAN NOT NULL DEFAULT FALSE`, no backfill; cast on `Sensor`.
- Routes (anonymous, product-domain): `GET /api/public/graph/bootstrap`, `GET /api/public/graph/sensors/{sensor}/series`.
- `DomainEventBroadcastConsumer` dispatch now gates the public channel on `PublicGraphVisibility::isPublic($reading->sensor)`; restricted facts still ACK + private-broadcast, no public.
- Tests: `PublicGraphControllerTest` (11: empty/null-default, only-public grouping, empty-device omission, status-independence, 404 restricted/guessed, malformed-timestamp reject, from<to reject, ordered points, **stats-not-capped-at-60**) + 2 consumer tests.

### Stage 6.1 — DRY chart owner  ✅ code + front tests green
- NEW `front/src/components/charts/SensorReadingChart.vue` (presentation-only; no HTTP/Echo) + `sensorChartViewModel.js` (pure normalizer) + `front/src/utils/chartTheme.js` (reuses existing SINOA `--app-*` tokens, no invented colors).
- `SensorChart.vue` / `SensorReadingsChart.vue` reduced to thin adapters. `SensorChart.vue` kept (not deleted) because `verify-phase4.mjs` requires the path.
- a11y: `role="img"`, textual summary, `.visually-hidden` value list, on-screen `<dl>`; no threshold/staleness/cadence/quality/precision synthesis.
- Tests: chart + viewmodel + chartTheme + adapters. **Agent-executed vitest: 50 unit + 17 realtime + build green.**

### Stage 6.2 — live projection + graph wiring + DELETE sensor polling  ✅ code
- NEW `front/src/api/graph.js` — `getGraphBootstrap`, `getGraphSeries(sensorId,{from,to,signal})`, `graphPointToReading`.
- NEW `front/src/stores/sensorReadings.js` — live projection keyed by `sensorId` (dedupe-by-id, chronological, bounded tail; `clearSensor/clearAll`).
- NEW `front/src/stores/graphSeriesQuery.js` — historical layer keyed by scope+sensor+window; per-key `AbortController` cancels superseded windows; stores server `stats` as-is (never truncates).
- `useSensorRealtime.js` recovery snapshot now uses graph-series (bounded, one-shot on reconnect/visibility/auth), not periodic latest-readings; auth resync clears projection.
- **`SensorMonitorBoard.vue` polling DELETED**: `pollTimer`, `startPolling`/`stopPolling`, `refreshVisibleMonitors`, `refreshMonitor`, `pollInterval` prop, inline chart — all removed; renders via shared chart owner.
- `DashboardView.vue` bootstraps from `getGraphBootstrap()` instead of `/dashboard/public`; dropped `getPublicConfig`/pollInterval.
- Tests: projection dedup/order/replay, query cancellation, stats-not-capped, no-timer assertion on `SensorMonitorBoard`.

### Stage 7 (BACKEND ONLY) — protect alert transport  ✅ code
- `GET /api/alerts/active` moved under `auth:sanctum` (guest → 401). "public API = graph-only" is now true for this route.
- Alert events `NewAlertTriggered` / `AlertResolved` → `PrivateChannel('alerts')` (wire `private-alerts`); `routes/channels.php` authorizes any authenticated user.
- Tests: `AlertTransportAuthorizationTest` (guest 401, PAT 200, private-channel auth) + `EventEnvelopeTest` update.

### Stage 8.2 — email off the synchronous path  ✅ code
- NEW `back/app/Jobs/SendDangerAlertEmailJob.php` (`ShouldQueue`, dispatched `afterCommit`); `AlertObserver` no longer sends inline. Reused Laravel queue — **no new consumer group** (over-engineering rejected per `audit §15a`).
- `NotificationService` rate-limit gap fixed: failed send `Cache::forget`s the reservation so a legitimate retry is not suppressed (`audit §13`).
- Tests: `AlertEmailAsyncDeliveryTest`, `NotificationServiceRateLimitTest`, + updated `AlertEmailTest`/`DangerAlertEmailTest`.

---

## NOT DONE / PENDING

- **P0 — `.env.host-backup` secret still in repo + history** (added in `789efbf`; live `APP_KEY`/`API_KEY`/`PUSHER_APP_SECRET`). Purge requires history rewrite + force-push, blocked by operator push-hold. Credentials must be rotated at source. `git-filter-repo` is installed and ready. **Top gate blocker — open.**
- **Stage 7 FRONTEND — NOT DONE (agent cancelled, nothing landed).** Still required: `useAlertsRealtime.js` → private `alerts` channel (`{privateChannel:true}`, authed-only) or the front stops receiving alerts now that the backend broadcasts on `private-alerts`; `stores/alerts.js` sole idempotency + `clearAuthorizedState()`; DELETE `AppLayout.vue` ~10s + `ActiveAlertsCard.vue` ~5s timers; `<AlertToast v-if="isAuthenticated">`; swap alert config from `/api/config/public` → `/api/config/runtime`.
- **`GET /api/config/runtime` (auth:sanctum → `{alert_sound_enabled}`) — NOT DONE (agent cancelled).** Enabler for removing `/api/config/public`.
- **Stage 8.1 device frontend projection — not started** (`device.status.changed` → authorized dashboard/list/detail; backend `DeviceStatusUpdated`+`DeviceService.changeStatus` already exist).
- **Stage 8.3 webhook — deferred (YAGNI).** PLAN says define-boundary-only until a real destination is approved; no code written.
- **Legacy Blade `app.blade.php`** does an unauth `fetch('/api/alerts/active')` + public `alerts` subscribe → goes silently dark after Stage 7 backend. Expected; part of the deferred legacy-Blade retirement.
- **`AlertFeedController.php`** is dead code (duplicates `ApiAlertController::active()`) — flagged for a later DRY pass, not deleted speculatively.
- **All backend suites + MySQL EXPLAIN unrun here** (no php/mysql) — GAP below.

---

## GAP — commands for the run-machine

```bash
# Backend (sqlite isolated, matches CI):
cd back
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan test
# focused: PublicGraphControllerTest, DomainEventBroadcastConsumerTest, BroadcastChannelAuthorizationTest,
#          EventEnvelopeTest, AlertTransportAuthorizationTest, AlertEmailAsyncDeliveryTest,
#          NotificationServiceRateLimitTest, ReadingTimeSemanticsTest, SensorReadingRangeIndexTest
vendor/bin/pint --test
# MySQL cutover + index proof:
php artisan migrate --force
#   SHOW INDEX FROM sensor_readings WHERE Key_name='sensor_readings_sensor_time_id_idx';
#   EXPLAIN SELECT id,sensor_id,value,reading_time FROM sensor_readings
#     WHERE sensor_id=? AND reading_time >= ? AND reading_time < ? ORDER BY reading_time,id;
#   -> expect the composite index chosen, no filesort.

# Frontend:
cd front && npm run test:unit && npm run test:realtime && npm run build
```

## Gate verdict
**PRE-STAGE-6 GATE:** still FAIL on P0 (secret) + unrun backend evidence. Stage 6 core + Stage 7 backend + Stage 8.2 are code-complete; **Stage 7 frontend, `/config/runtime`, Stage 8.1 remain.** Do not freeze a Stage-6 baseline SHA until the secret history rewrite + force-push happen (they change SHAs) and the run-machine turns the GAP commands green.
