# Pre-Stage-6 Evidence Ledger

**Branch:** `refraccion`
**Starting SHA (reviewed committed range ends at):** `00b560c`
**Baseline commit:** `correcciones documentales, se establecen issues bloqueantes previas a stage 6`
**Execution environment:** THIS session/machine has **no `php`, no Composer/`vendor/`, no Docker, no MySQL client, no `vitest`** on PATH and no package-registry access. It is a code/audit environment only. Every test below was **written and reviewed against source (TDD-style) but NOT executed here**. No result was fabricated; unrun assertions are marked GAP with the exact command to run in a real runtime.

> **PRE-STAGE-6 GATE: BLOCKED** — blocked by Time semantics = classification **C** (see Task 3), pending runtime execution, guest/auth transition isolation defects, incomplete frontend private-sensor realtime, a known private-channel test expectation issue, and unresolved `/config/public` replacement. Do not treat this preflight as Stage-6 completion.

---

## Task 0 — Gate S5-R evidence

- `git branch --show-current` → `refraccion`
- Planning baseline: `44e3bb865ec76d1a98925d706e25ccadbb6f5785`.
- Reviewed implementation commit under audit: `00b560c`.
- Verified runtime SHA: pending real runtime execution.
- **GAP (runtime):** the Stage-5 proof commands cannot run here. Run in a runtime env:
  ```
  cd front && npm run test:unit && npm run test:realtime && npm run build && npm run audit:events
  cd back  && APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan test --filter=DomainEventBroadcastConsumerTest
  ```
- Known intentional polling still present (correct pre-Stage 6/7): `SensorMonitorBoard.vue` 2s, `ActiveAlertsCard.vue` 5s, `AppLayout.vue` 10s (authenticated only after Task 7). Do NOT use the global no-polling network audit as a Stage-5 blocker yet.

## Task 1 — Vue SPA = single public dashboard entry point  ✅ code-complete
- `back/routes/web.php`: `/` and `/dashboard` now `redirect()->away(config('app.front_url').'/dashboard')`; name `dashboard` preserved.
- Deleted: `DashboardController.php`, `dashboard.blade.php` + 4 partials (rendered metrics/alerts/device-sensor data + Pusher script — now gone from backend).
- Test: `PublicDashboardEntryPointTest.php` (redirect for `/` and `/dashboard`). **GAP: not executed.**

## Task 2 — Anonymous/non-session route classification  ✅ code-complete
- `/api/iot/sensors` = credentialed (X-Device-Key/api_key vs `config('app.api_key')`), 401 on missing/wrong key. NOT a guest product API. No second auth scheme added.
- `/api/health` = anonymous liveness only; payload exactly `{status, app, timestamp}`, no product data.
- `/api/dashboard/public`, `/api/config/public`, `/api/sensors/{id}/latest-readings`, `/api/devices/{device}/sensors` = transitional; **NOT removed** (Stage 6 removes atomically after replacement).
- `/api/alerts/active` = Stage 7 auth migration (still anonymous — the "public API = graph-only" claim is FALSE until Stage 7).
- Test: `PublicSurfaceClassificationTest.php`. **GAP: not executed.** `route:list --path=api` derived by hand from source (artisan unavailable).

## Task 3 — Reading-time semantics  ⛔ BLOCKS STAGE 6 (classification C)
- **Classification: C — mixed/ambiguous.** Two source-derived reasons:
  1. `SensorReadingService::createReading()` input asymmetry: a legacy `Y-m-d H:i:s` string is parsed in `America/Bogota` (config default) → Bogotá wall-clock; a `DateTimeInterface`/Carbon UTC instance is stored with its literal digits then re-read as Bogotá → **~5h instant drift**. Same column, two semantics by caller overload.
  2. `reading_time` is a SQL `TIMESTAMP` (session-tz dependent) and `config/database.php` never sets the `timezone` key → MySQL session `time_zone` unpinned vs `America/Bogota`.
- Files: `ReadingTimeSemanticsTest.php` (6 tests), `InspectReadingTimeSemantics.php` (`diagnostics:reading-time-semantics` cmd), `docs/implementation/reading-time-semantics.md` (freeze doc).
- **Required before Stage 6:** run GAP-0/1/3 in a real MySQL env, then decide historical migration/normalization + new-write rejection rule. Rule frozen: never append `Z` to offsetless timestamps to fake UTC.
- **Commit-review correction:** this is a current pipeline risk, not only a latent overload risk. `RawReadingNormalizer` can pass `RawSensorEvent.received_at` Carbon values or raw payload timestamp strings into `SensorReadingService`; extend the test matrix through absent timestamp, legacy offsetless timestamp, RFC3339 `Z`, and explicit-offset inputs.
- **GAP (runtime):**
  ```
  cd back && php artisan test --filter=ReadingTimeSemanticsTest   # GAP-0
  cd back && php artisan diagnostics:reading-time-semantics       # GAP-1/3 (needs MySQL)
  # or: SELECT @@session.time_zone,@@global.time_zone,NOW(),UTC_TIMESTAMP();
  ```

## Task 4 — Graph-range composite index  ✅ code-complete (EXPLAIN pending)
- Migration `2026_09_09_000001_add_graph_range_index_to_sensor_readings.php`: index `sensor_readings_sensor_time_id_idx (sensor_id, reading_time, id)`. No equivalent pre-existed (only single-col `sensor_id` FK index).
- Test `SensorReadingRangeIndexTest.php` asserts real index metadata via `PRAGMA` (name + column order), not file existence.
- Sample/aggregation limits (raw|1m, 2000, 50000) are **NOT frozen** — Stage 6 selects them from measured EXPLAIN cost.
- **GAP (runtime):** `php artisan migrate` + `EXPLAIN` on MySQL (chosen key, rows examined, filesort check).

## Task 5 — Authenticated restricted-sensor realtime path  ⚠ partial
- `channels.php`: added private `Broadcast::channel('sensor.{sensorId}', ...)` (authenticated + sensor exists; reuses app's no-per-sensor-ACL design). Public channel intentionally kept.
- `NewSensorReading`: constructor flags `includePublicChannel`/`includePrivateChannel`; `broadcastOn()` builds channel set from flags; **no DB/policy lookup in event**.
  - Reasoned deviation: `includePrivateChannel` default = **false** (not true) so the frozen `EventEnvelopeTest` bare-constructor `broadcastOn()->name` doesn't break on an array. Production dispatch site passes **both = true** explicitly. Delivery matrix identical to spec.
- `DomainEventBroadcastConsumer`: dispatch passes both channels + comment marking where Stage 6 `PublicGraphVisibility::isPublic()` plugs in. Transaction/idempotency/XACK/retry/DLQ untouched.
- Delivery matrix (mechanism ready; flag wired in Stage 6): public → guest public YES + auth private YES; restricted → guest public NO + auth private YES.
- **Open defects:** frontend sensor realtime still uses the public `echo.channel()` path; the authenticated Vue graph does not yet consume `private-sensor.{id}`. Any private-channel assertion must expect Laravel's `private-sensor.{id}` channel name, not `sensor.{id}`. Add a bearer-token `POST /api/broadcasting/auth` test for the real SPA/Sanctum flow.
- Tests: `BroadcastChannelAuthorizationTest.php` (new), `DomainEventBroadcastConsumerTest.php` (extended). **GAP: not executed.** Known expectation audit required before counting these tests as green.

## Task 6 — Retire legacy Blade sensor read surfaces  ✅ code-complete
- `web.php`: `sensors.index`/`sensors.show` now authenticated redirects to SPA; write/download/filter/edit routes preserved.
- Deleted `sensors/index.blade.php` (public Pusher `sensor.{id}` sub, `data.unit`) and `sensors/show.blade.php` (3s latest-readings poll); removed unreachable `SensorController::index()/show()`.
- Coordinator cleanup: removed dead `HomeController::dashboard()` (pointed at deleted view). No dangling `view('dashboard')` remains; `route('dashboard')` resolves to the redirect closure.
- Test `LegacySensorSpaRedirectTest.php`. **GAP: not executed.**

## Task 7 — Isolate guest graph mode from alerts/config  ⛔ broken on auth transitions
- `AppLayout.vue`: alert config load / active-alert fetch / subscribe / 10s poll is guarded only on initial mount. Add an auth-state watcher with idempotent `startGlobalAlerts()` and `stopGlobalAlerts()` so login starts authorized alerts and logout clears interval/subscription/projection while the layout remains mounted.
- `useAlertsRealtime`: must not resubscribe after `auth:changed` when the new state is guest.
- `NavBar.vue`: guest alert-badge fetch is guarded, but the guest DOM still renders `Alertas activas: 0` or can render stale authenticated `unresolvedCount` after logout. Remove the guest count entirely or render a neutral access-required/omitted slot; add guest and auth-to-guest DOM assertions.
- `AlertToast`: must be hidden/unmounted or cleared when scope becomes guest so an authenticated toast cannot remain visible after logout.
- No protected replacement config path exists; `/api/config/public` cannot be removed until authenticated alert UI reads a scoped runtime config endpoint.
- Tests `AppLayout.test.js`, `NavBar.test.js`. `npm run build` **PASS** (vite present). `vitest` **GAP** (not installed, no registry).

## Commit-review correction — 9 September 2026

Reviewed through `00b560c`; see `front_rebuild_plan/PRE_GATE6_COMMIT_REVIEW_2026-09-09.md`. The preflight does not implement the graph-only public boundary. Added blockers: private-channel tests must expect `private-sensor.{id}`; the time defect reaches `RawReadingNormalizer`; alert isolation is broken across login/logout; authenticated sensor realtime does not yet consume private channels; `NewSensorReading` is still default-public; `/config/public` lacks an authenticated replacement; remaining Blade sensor CRUD needs an auth-continuity decision. Guest isolation is therefore not complete, and the final public route/visibility cutover remains Stage-6 work.

## Task 8 — Patch executable docs  ✅ done
Applied as an authoritative **v1.3 pre-Stage-6 corrections** addendum block (override pattern, matching the existing v1.2 idiom) at the top of: `PLAN.md`, `FRONT_REBUILD_PLAN_ADJUSTMENTS_v1.1.md`, `PUBLIC_GRAPH_VISIBILITY_AGENTIC_EXECUTION_PLAN.md`, `SINOA_..._v2.1_PUBLIC_REALTIME.md`. Corrections applied: /iot/sensors credentialed; /health liveness exception; single canonical Vue entry (drop "choose either"); transitional APIs removed atomically not before; drop unmeasured graph limits; ownership split (live projection key=sensorId; historical query key=scope+sensorId+from+to+aggregation; Pinia does NOT own subscription release; channelRegistry/useSensorRealtime owns release); private auth sensor channel; legacy Blade retired before payload contraction; Stage 6 starts only on GATE: PASS.

---

## PRE-STAGE-6 GATE ledger

| Gate group | Verdict | Note |
|---|---|---|
| Canonical entry point | ✅ code / ⏳ runtime | backend emits no dashboard HTML; run redirect test |
| Route classification | ✅ code / ⏳ runtime | 401 tests + route:list written |
| **Time** | ⛔ **BLOCKED** | classification **C**; needs MySQL probe + migration decision |
| Query index | ✅ code / ⏳ EXPLAIN | index present; benchmark pending |
| Compatibility (Blade retire) | ✅ code / ⏳ runtime | public Blade dashboard + sensor read pages gone |
| Authenticated realtime | ⚠ partial / ⏳ runtime | backend private channel exists; frontend private consumer + PAT auth test pending |
| Guest isolation | ⛔ broken / ⏳ vitest | mount-only guard misses login/logout teardown and still renders/stores alert identity |
| Runtime config | ⛔ blocked | `/config/public` still feeds authenticated alert UI; protected replacement pending |
| Ownership/docs | ⚠ partial | v1.3 addendum applied; contradictory executable instructions must be physically removed |

**Overall: BLOCKED.** Stage 6 may not begin until, in a real runtime: (1) Task 3 classification C is resolved with a historical migration/normalization decision that includes `RawReadingNormalizer`, (2) every written suite runs green after correcting the private-channel expectation, (3) EXPLAIN confirms the index path, (4) guest/auth alert transitions clear polling, subscriptions, counts, and toasts, (5) authenticated sensor realtime consumes private channels, and (6) `/config/public` has a protected replacement/removal path. The graph-only route and visibility boundary are Stage-6 work, not completed by this preflight range.
