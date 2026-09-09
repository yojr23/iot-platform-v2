# FRONT REBUILD PLAN — Adjustments v1.1

Repository: `yojr23/iot-platform-v2`  
Branch: `refraccion`  
Purpose: align the SINOA Lab Blue Workspace plans with the existing repository, the approved public graph scope, and the current realtime architecture.

## v1.3 pre-Stage-6 corrections — authoritative over v1.2 and below

Evidence: `docs/implementation/pre-stage6-evidence.md`, `docs/implementation/reading-time-semantics.md`.

- **Canonical public entry is decided: the Vue SPA at `FRONT_URL/dashboard`.** Remove "choose either / route anonymous `/dashboard` to SPA *or* reduce Blade" language — the Blade dashboard is retired (backend redirects `/` and `/dashboard` to the SPA; controller + view deleted).
- **`/api/iot/sensors` = credentialed non-session ingestion endpoint** (X-Device-Key/api_key, 401 on missing/wrong), not a guest inventory API. **`/api/health` = anonymous liveness exception** only.
- **Transitional public APIs are removed atomically with the Stage 6 replacement, not before** (no guest outage). `/api/config/public` stays until `DashboardView` stops using its polling interval.
- **Graph limits are not facts.** Any `raw|1m` / 2,000 / 50,000 bounds are illustrative until Stage 6 measures EXPLAIN cost. Freeze only timestamp grammar (post-probe), half-open windows, DB-as-truth, bounded query, no silent truncation.
- **Ownership:** live projection store key = `sensorId`; historical query layer key = `authorizationScope + sensorId + from + to + aggregation`; **Pinia does not own subscription release** — `channelRegistry`/`useSensorRealtime` do.
- **Authenticated restricted sensors** use the authorization-enforced private `sensor.{id}` channel; legacy Blade sensor read pages are retired before payload contraction (done).
- **Time semantics = classification C (mixed/ambiguous); Stage 6 BLOCKED** until resolved. Stage 6 begins only on `PRE-STAGE-6 GATE: PASS`.

## v1.2 implementation audit — authoritative corrections

This addendum is based on the checked repository, not on the mockup. It overrides any conflicting Stage 6–10 instruction below or in the companion SINOA plan.

- **One public product entry point, before route deletion.** `back/routes/web.php` currently makes the Blade `DashboardController@index` anonymous and renders global metrics, active alerts, devices, and sensors; `front/src/views/DashboardView.vue` is a separate SPA dashboard that currently calls the same broad public API. Select the deployed Lab Blue entry point and make anonymous `/dashboard` reach only it before deleting `/api/dashboard/public`. The other route must be retired, redirected, or made authenticated in the same release. A broad Blade dashboard cannot wait for Stage 10 because it already violates the guest scope.
- **Anonymous product APIs are graph-only, not merely Vue callers.** Remove/protect `/api/config/public` with the polling interval and alert settings when Stage 6 removes its guest use. Give `/api/iot/sensors` an explicit ingestion credential/internal boundary review. `/api/health` is allowed only as an explicitly owned infrastructure liveness endpoint, not as a dashboard data API; protect or relocate it if the deployment policy has no liveness exception. The end-state guest product surface is exactly graph bootstrap, bounded graph series, and approved reading delivery; `/api/alerts/active` and the `alerts` channel complete their move in Stage 7.
- **UTC is blocked until proved.** The app defaults to `America/Bogota`; `sensor_readings.reading_time` is a Laravel `timestamp` without a documented UTC write/read invariant, and the legacy sensor-ingestion endpoint accepts `Y-m-d H:i:s`. Stage 6 must first run a DB/application probe, establish migration/normalization rules for historical values, and reject/normalize ambiguous new input. Only then use the frozen second-precision UTC format `YYYY-MM-DDTHH:mm:ssZ` and half-open `[from,to)` graph windows.
- **The graph query needs a source-of-truth and index.** Add and benchmark `sensor_readings(sensor_id, reading_time, id)` before public range queries. The database is authoritative for every series/statistic; `SensorReadingProjectionService` is a 120-entry internal latest-reading cache and may not silently supply incomplete graph history.
- **No invented scientific semantics.** V1 supports returned-sample min/max/mean/count and last-observed time. It does not expose threshold bands, precision, quality flags, expected cadence, completeness, or a stale cutoff: no such public contract/owner exists. Show loading, no-data, last observed time, and browser transport status. A later feature may add those fields only with a named domain owner, migration/configuration source, public-safety decision, and tests.
- **Visibility is current sensor-wide policy.** The consumer checks the current explicit flag at delivery; therefore enabling a sensor makes its bounded stored history and subsequently delivered readings eligible, and disabling suppresses not-yet-broadcast queued facts. A time-bounded visibility rule would require a new policy/schema and is not implied here.
- **Payload shrink requires legacy migration.** `NewSensorReading` presently exposes `unit` to the Blade sensor list and the public Blade dashboard subscribes to every sensor. Retire/redirect the public Blade dashboard and update any retained authenticated subscriber to use its authorized rendered metadata before removing event `sensor_name`, `sensor_type`, `unit`, `device_name`, and `lab_name`. Never retain an enriched public-event fallback.
- **Guest UI must match the approved Lab Blue graph workflow.** Guests and authenticated users have the same responsive graph shell, toolbar, device/sensor selection, range controls, chart hierarchy, primary reading, and graph error/empty treatment. They may have different page composition and capability slots—alerts/events/preferences/admin render an access-required or omitted state—without a second graph engine or a login wall. Verify this through paired guest/auth screenshots and network traces at each target width.

The requested `ponytail`, `caveman`, and `zero-hallucination` names do not exist as skills in the supplied marketplace graph. In this plan they are implemented as review controls: one small end-to-end vertical slice before expansion, one owner per concern, no fallback duplication, and no assertion beyond repository evidence.

> **Decision, 9 September 2026 — guest public graph scope.** This document overrides every conflicting public-data statement in the SINOA implementation plan and its source mockup. Guests receive the same Lab Blue dashboard workspace and graph workflow—responsive shell, toolbar, selectors, current reading, ranges, chart list, main chart, scientific graph details, and an ephemeral chart draft—without a login wall or a second legacy dashboard. Public domain APIs and public realtime channels exist only for approved graph catalog/metadata, bounded graph series, and public sensor-reading updates. They do not expose global metrics, arbitrary inventory/history, alerts, events, device status, preferences, administration, or any restricted sensor data. Restricted dashboard regions have an explicit guest access-required state with no sensitive count, name, severity, value, or timestamp; unsupported mockup controls are omitted rather than inert.

## Closed decision — explicit per-sensor public graph visibility

This is a closed architectural decision, not a frontend convention or a proposed `is_public` variation.

- Persist exactly `sensors.public_monitoring_enabled` as a `BOOLEAN NOT NULL DEFAULT FALSE` migration. Do not backfill all existing sensors to `true`; production enablement is an explicit administrative action or reviewed list, and fixtures/seeders mark only intentional demo sensors.
- The visibility scope is **Sensor**. `sensor.status`, `device.status`, `device.is_active`, Lab, and Device do not decide public visibility. A public sensor with no new sample remains visible with its last-observed/no-data state; it does not disappear, receive invented samples, or receive a `stale` label until an explicit cadence policy exists.
- The single owner is `App\Services\Monitoring\PublicGraphVisibility`, with only `isPublic(Sensor $sensor): bool`, `publicSensorsQuery(): Builder`, and `requirePublic(Sensor $sensor): Sensor`. Its rule is exactly `$sensor->public_monitoring_enabled === true`. It must be the sole policy decision used by REST and browser delivery.
- Extend the existing protected admin create/update flow (`validatedSensorPayload`, `Sensor`, `SensorResource`, and the existing sensor form) to manage this boolean. Do not add a separate `/public-visibility/...` endpoint or a second administrative owner.
- The durable fact remains unconditional: `SensorReadingService` creates `sensor.reading.created` and the outbox/Redis Stream retains it for every sensor. Visibility is evaluated only at the browser-delivery boundary: `DomainEventBroadcastConsumer` calls `PublicGraphVisibility` before dispatching the public `NewSensorReading` representation. Restricted readings are acknowledged as delivered domain facts but emit **no public broadcast**. `NewSensorReading` performs no visibility lookup.
- The current `NewSensorReading` payload contains `sensor_name`, `sensor_type`, `unit`, `device_name`, and `lab_name`. The public graph event must instead contain only the reading identity, sensor ID, value, reading timestamp, and versioned envelope. The graph resolves labels/unit from the approved bootstrap; it must not receive Lab or extra inventory context through realtime.
- Reuse the existing internal `SensorReadingProjectionService`/Redis key as server infrastructure after the REST policy check. Do not create `PublicSensorReadingProjection`, a `public:sensor:*` cache, or a public-only duplicate stream.
- Public REST first queries `publicSensorsQuery()` with `sensorType` and `device`, then projects the minimal graph DTO and groups it by device. Devices with no public sensors are absent. Graph-series route binding must call `requirePublic`; restricted and guessed IDs return `404`, never `403`.

The immediate public DTO is deliberately limited to fields the current schema actually owns: device `id`/`name`, sensor `id`/`name`, and `unit` from `sensorType`, plus a stable default public sensor identity. Precision, thresholds, cadence, and quality fields are **not** currently modelled in the repository; do not synthesize them merely to match the mockup. Add any of them later only with a named domain owner, migration/configuration source, contract, and tests.

```text
SensorReadingService -> sensor.reading.created -> outbox -> Redis Stream
                                                      |
                                                      v
                                     DomainEventBroadcastConsumer
                                                      |
                                                      v
                                      PublicGraphVisibility (only owner)
                                        |                         |
                              public_monitoring=true             false
                                        |                         |
                                        v                         v
                            public sensor.{id} event      no public delivery
```

## Mandatory adjustments

1. **Preserve public `/dashboard`.**
   - Guests must continue to view realtime public sensor charts without login.
   - Do not add `requiresAuth` to basic public monitoring.

2. **One dashboard, two capability modes.**
   - Guest and authenticated users share the graph engine, realtime pipeline, sensor projection, chart primitives, scientific graph semantics, tokens, and responsive design system.
   - They do **not** need the same page composition or component tree. Capability-specific regions may differ as long as guest graph monitoring remains the same Lab Blue visual experience and no guest-only data path is created.
   - Authentication adds persistence, restricted resources, and privileged actions only.

3. **Public realtime is graph-only P0.**
   - The only public dashboard domain stream is the approved sensor-reading stream, currently compatible with `sensor.{id}`.
   - A server-owned visibility policy must filter the graph bootstrap, graph-series response, and broadcast producer consistently. A restricted sensor never appears in a guest response or emits a public reading payload.
   - `alerts` and `device-status` are not guest channels. If retained for authenticated capabilities, they must use the appropriate authorized transport.
   - Registration is not required for public live graph updates.

4. **Public API surface is graph-only.**
   - Replace the broad public dashboard response with a graph bootstrap containing only permitted device/sensor labels and IDs, current `sensorType.unit`, and a default graph identity. Do not fabricate precision, thresholds, cadence, or quality metadata that the current schema does not own.
   - Provide one bounded public graph-series operation with precise UTC `from`/`to`, aggregation, maximum window/payload, and gap semantics. It supplies initial hydration and lifecycle recovery; it is not a polling endpoint.
   - Retire or scope-enforce public `latest-readings` and `devices/{device}/sensors` behavior so they cannot become general public inventory/history APIs.
   - Existing health or deployment configuration endpoints are outside this dashboard decision; do not broaden them as part of this rebuild.

5. **No frontend polling or polling fallback.**
   - Initial bounded REST hydration is allowed.
   - Reconnect/visibility/auth lifecycle can trigger one bounded recovery snapshot.
   - Steady-state graph updates come from Echo/WebSocket.

6. **Reuse current owners instead of creating a parallel frontend.**
   - `front/src/views/DashboardView.vue`
   - `front/src/components/dashboard/*`
   - `front/src/realtime/echo.js`
   - `front/src/realtime/channelRegistry.js`
   - `front/src/realtime/useSensorRealtime.js`
   - `front/src/stores/auth.js`
   - existing API adapters.
   - `front/src/realtime/useAlertsRealtime.js` and `front/src/stores/alerts.js` remain owners for authenticated alert capabilities; guest graph rendering must not depend on them.

7. **Gate 6 supplies the sensor projection foundation.**
   - The rebuild must consume one shared Pinia sensor-reading projection keyed by authorization scope, sensor ID, and resolved graph window.
   - The projection owns request generation/cancellation, bounded ordered merge, de-duplication, last-observed presentation, recovery, and subscription release.
   - The current `SensorMonitorBoard` 2-second polling loop is transitional and must be removed during the realtime cutover.

8. **Clarify Pinia guidance.**
   - Do not add another state-management framework.
   - Pinia is appropriate for shared cross-component domain projections such as sensor readings and alerts.
   - Transient UI state stays local/composable.

9. **Guest workspace behavior.**
   - Guests may add/remove/reorder/select/range-configure public charts in an ephemeral in-memory draft.
   - Guest Save opens authentication/persistence flow; it must not block live monitoring.
   - Do not write guest drafts to server preferences or display `Saved` without server confirmation.

10. **Authenticated workspace behavior.**
   - Use the existing dashboard preference owner.
   - Extend it rather than creating a second preferences API.
   - Rich revision/schema/conflict semantics remain a backend extension where not yet supported.

11. **Server-owned public scope.**
   - Vue must not be the security boundary.
   - Public graph bootstrap/series endpoints and sensor-reading payloads expose only data explicitly approved for public graph monitoring.
   - Alerts, events, device status, preferences, administrative data, and restricted sensors require authenticated authorization.

12. **Guest UI policy.**
   - Match the approved Lab Blue layout and chart-first responsive hierarchy in both modes.
   - The guest header shows a sign-in action, never an `Admin` identity; guest navigation includes Dashboard and real public destinations only.
   - Search, laboratory/system selectors, chart gear/kebab menus, reports, rules, and configuration are excluded from P0 until an API, authorization rule, and acceptance criterion exist.
   - `CriticalAlertBanner`, `ActiveAlerts`, and `RecentEvents` have a guest access-required/omitted state. They must not issue public requests or subscribe to public alert/event channels.

13. **Time-range implementation must use real backend contracts.**
   - RF03 ranges `1m`, `5m`, `1h`, `6h`, `24h` cannot be simulated by arbitrary client-side sample counts.
   - The graph-series contract must accept precise UTC `from`/`to`, constrain window/payload size, declare aggregation, preserve gaps, and return enough metadata for truthful statistics and last-observed presentation.

14. **Add guest-specific verification.**
   - No-token realtime chart update.
   - No recurring graph/latest-reading GETs after hydration; lifecycle recovery is bounded and observable.
   - Restricted sensor absent from the graph bootstrap, graph-series response, and public broadcast producer; guessing its ID/channel exposes no data.
   - Guest requests no alert, event, device-status, preference, or administration API and opens no such public channel.
   - Login/logout does not duplicate subscriptions.
   - Guest draft survives auth transition where practical.

## Updated execution order

```text
verify current SHA / owners
→ define and test server-owned public graph visibility
→ replace broad public dashboard data with graph bootstrap + bounded graph series
→ Gate 6 shared sensor-reading Pinia projection
→ remove graph polling and prove no recurring network reads
→ SINOA tokens + responsive Lab Blue shell
→ public realtime graph vertical slice
→ scientific stats / ranges / inspector
→ guest workspace editing
→ authenticated persistence enhancement
→ authenticated alerts/events + authorized extensions
→ mobile/accessibility
→ verification and rollout
```

## Superseded architecture sketch

```text
PUBLIC REST HYDRATION ───────┐
                             ▼
PUBLIC ECHO CHANNELS → SENSOR/ALERT PINIA PROJECTIONS → LAB BLUE WORKSPACE
                             ▲
AUTHENTICATED APIs ──────────┘
        (preferences / restricted capabilities)

This earlier broad public-transport sketch is superseded by the graph-only invariant below.
```

## Architecture invariant — public graph only

```text
PUBLIC GRAPH BOOTSTRAP + BOUNDED SERIES
                    |
                    v
PUBLIC sensor.{id} READING EVENTS -> SENSOR-READING PINIA PROJECTION -> LAB BLUE GRAPH WORKSPACE
                    ^                                               |
                    |                                               |
AUTHENTICATED APIs / AUTHORIZED STREAMS -> preferences, alerts, events

Guest and authenticated users share one graph implementation.
Only the server decides whether a sensor may enter the public graph path.
```

## PLAN.md reconciliation — Stages 6 through 10

The following Stage 6–10 instructions are authoritative when `PLAN.md` describes a broader public dashboard, a legacy polling model, or a component that the Lab Blue rebuild replaces. They preserve the underlying reliability work in `PLAN.md`: durable reading publication, one Echo owner per tab, lifecycle-triggered recovery, idempotent projections, no frontend polling, and end-to-end evidence remain required.

### Stage 6 — public graph cutover and Lab Blue graph foundation

Stage 6 begins only after the Stage 5/Gate S5-R evidence for shared Echo ownership, reference-counted release, and bounded lifecycle recovery is current. A prior plan must not be treated as proof that this prerequisite passed.

1. **Establish the exact server graph boundary first.** Add `sensors.public_monitoring_enabled BOOLEAN NOT NULL DEFAULT FALSE`, cast it to a boolean on `Sensor`, and make `App\Services\Monitoring\PublicGraphVisibility` the only owner of the exact `=== true` decision. It must expose only `isPublic`, `publicSensorsQuery`, and `requirePublic`. It controls bootstrap inclusion, graph-series access, and whether `DomainEventBroadcastConsumer` dispatches the public `NewSensorReading` event. The consumer still acknowledges every durable fact; restricted readings simply produce no public event. Shrink the current event payload from its sensor/device/lab metadata to reading identity, sensor ID, value, timestamp, and envelope; bootstrap owns public labels/unit. Do not put a database/policy lookup into `NewSensorReading`, filter `SensorReadingService`/the outbox, infer visibility from operational status, or create public Redis storage.
2. **Constrain the actual public routes, not only the Vue caller.** Replace `/api/dashboard/public` with a minimal graph bootstrap built from `publicSensorsQuery()->with(['sensorType', 'device'])`; project only device `id`/`name` and sensor `id`/`name`/`unit`, omitting devices without approved sensors. Add a bounded public graph-series route that calls `requirePublic` after route binding and returns `404` for restricted or guessed IDs. Retire the public generic latest-readings and device-sensor routes (retain a protected equivalent only where current authenticated views need it). Do not create public precision/threshold/cadence/quality data without a real server owner. The existing `/api/alerts/active` exposure is an explicit Stage 7 migration item: it is not a Gate 6 dependency, but Gate 6 must not claim that the final graph-only public API target is fully deployed until Stage 7 moves it behind authentication.
3. **Consolidate chart ownership before live-data wiring.** Reuse one chart owner or focused chart helpers for the current `SensorChart.vue` and `SensorReadingsChart.vue` responsibilities. Its input is an already-normalized graph view model; it does not call HTTP or Echo. Resolve SINOA CSS tokens into Chart.js options rather than introducing a second hardcoded palette.
4. **Replace the legacy monitor reading model with one scoped projection.** The Pinia reading projection is keyed at minimum by authorization context, sensor ID, resolved UTC window, and aggregation. It owns cancellation, current-request identity, sample normalization, deterministic `reading_id`/timestamp/sequence merge, bounded memory, freshness, lifecycle recovery, and subscription release. It must not retain data from a revoked authenticated scope after logout.
5. **Do not carry the legacy 60-point cap into scientific correctness.** The current cap is a legacy latest-reading display bound. A five-minute series at the mockup's two-second cadence can contain 151 samples, and longer ranges need server aggregation and/or display decimation. Preserve the valid source set needed for min/max/mean/count; cap or decimate only the rendered trace using an explicit, tested policy. Coverage/completeness is unavailable without an expected-cadence contract.
6. **Delete polling in the same change.** Remove `SensorMonitorBoard.vue`'s `pollTimer`, `startPolling`, `stopPolling`, `refreshVisibleMonitors`, `refreshMonitor`, polling-only prop, and the browser configuration that drives that interval. Replace `useSensorRealtime`'s bounded recovery call to `latest-readings` with the graph-series adapter. Keep the user-action debounce only if it still supports an allowed authenticated save flow.
7. **Preserve behavior while changing ownership.** Keep chart add/remove/reorder/select and the guest draft, but make the guest draft in-memory and public-source-only. Do not retain the current background local-storage/server autosave behavior. Guest Save opens login and preserves the draft where practical; authenticated save happens only after a confirmed preference response. Keep the Lab Blue graph controls/layout visually equivalent in guest and authenticated mode; vary only authorized capability slots, not the graph workflow.

**Stage 6 exit evidence:** the selected anonymous `/dashboard` entry uses the Lab Blue graph-only UI; no-token graph selection and live update work; `public_monitoring_enabled=true` source is present in bootstrap, returns graph series, and receives the reduced event; `false`/guessed/default-new sources are absent, return `404`, and receive no event; changing `status`, `device.status`, or `device.is_active` alone does not grant/revoke public visibility; a public offline source remains visible with last-observed/no-data truth and no inferred device failure; durable outbox facts exist for both visibility states; the agreed 1/3/5 public chart-widget cases work; duplicate/out-of-order/replayed reading handling; correct range/min/max/mean/count without invented threshold, quality, cadence, precision, or coverage semantics; public graph recovery without a periodic GET; zero recurring graph/latest-reading/config requests; guest logout/login does not duplicate a subscription; paired guest/auth graph UI and guest-only network traces pass at 320/390/768/1440. The old M03 monitor-header finding is re-tested only if equivalent controls remain; it is not copied blindly into a new component.

### Stage 7 — authenticated alert cutover

Stage 7 is no longer a public-dashboard dependency. Alerts, alert counts, recent events, and their transport are authenticated/authorized capabilities.

- Complete the authorized alert event model, including resolution transitions, then apply it through the existing `alerts.js` projection. A resolved alert cannot re-enter the active list, and count, banner, toast, sound, and rows use one idempotent store owner.
- Move any authenticated alert stream from the existing public `alerts` channel to an authorization-enforced transport before exposing it in the Lab Blue dashboard. Guests never subscribe, fetch `/api/alerts/active`, receive a count, or receive a banner identity; they see the document's access-required/omitted state.
- Delete the 10-second `AppLayout.vue` timer, 5-second `ActiveAlertsCard.vue` timer, and polling/fallback copy. Retain the toast-dismiss timeout and user sound preference because they are local UI behavior, not domain polling.

**Stage 7 exit evidence:** authorized create/duplicate/resolve-twice/bulk-resolve/reconnect cases agree; a guest network trace contains no alert request/channel; logout clears alert state; and the no-poll network assertion has zero periodic active-alert requests.

### Stage 8 — authenticated device projection and isolated external effects

Device status is not public graph data. Build its live projection only for authorized dashboard/list/detail consumers; do not add a public `device-status` subscription to make the mockup appear more complete. Public graph metadata may identify a device as the selected sensor's context, but it must not expose live status beyond what the graph contract explicitly permits.

Keep `PLAN.md`'s email and webhook work separated from the graph path: email delivery remains off the ingestion/browser-broadcast critical path, and the webhook work remains a disabled transport boundary until a destination and consumer are approved. Verify that an unavailable delivery destination cannot delay a reading or the approved public graph broadcast.

### Stage 9 — Lab Blue mobile and accessibility hardening

Run the existing evidence harness against the rebuilt Lab Blue guest and authenticated modes rather than repairing old monitor markup that no longer exists. Test the mockup's chart-first order at 320, 390, 768, 1024, 1280, and 1440 CSS pixels: one mounted main canvas on mobile, no hidden duplicate canvases/requests, 44 px interactive targets, logical focus order, accessible range controls, keyboard reorder alternatives, text equivalents for chart values, and no bottom-navigation obstruction. Reproduce M02/M03/M06/M07/M08 before changing code; route splitting remains measurement-driven.

### Stage 10 — reliability, security, and rollout proof

Keep `PLAN.md`'s CDC/relay, durable-consumer, retry/DLQ, observability, legacy-client retirement, and deployment-wide no-polling gates. Add the public graph boundary to that final proof:

- source and endpoint inspection show no broad public dashboard/inventory/history/alert/event/device-status exposure;
- backend authorization tests prove graph bootstrap, graph series, and broadcast production use the same visibility decision;
- a browser guest trace proves graph-only REST/WebSocket activity after initial hydration and bounded lifecycle recovery;
- an authenticated trace proves authorized alerts/events/preferences do not leak across logout, login, or scope change;
- dashboards distinguish transport connection, last-observed age, and sensor/domain state without claiming that a global alert makes the selected sensor critical.

Do not declare Stage 10 complete merely because the legacy Vue polling loops disappear. The deployment-wide proof still includes mandatory workers, durable delivery, real browser transport behavior, legacy Blade scope, observability, and the graph security evidence above.

## Revised Stage 6–10 sequence

```text
Stage 5 / Gate S5-R evidence current
  -> Stage 6.1 public graph visibility + graph API contract
  -> Stage 6.2 shared chart + scoped sensor-reading projection
  -> Stage 6.3 remove sensor polling + prove graph-only guest trace
  -> Lab Blue graph shell, ranges, statistics, inspector, guest draft
  -> authenticated preference extension
  -> Stage 7 authenticated alert/event cutover + remove alert polling
  -> Stage 8 authenticated device projection and isolated external effects
  -> Stage 9 Lab Blue responsive/accessibility hardening
  -> Stage 10 deployment-wide reliability, no-polling, and public-graph security proof
```

Do not parallelize Stage 6 and Stage 7 while they change shared Echo/auth lifecycle or dashboard composition. The email/webhook boundary in Stage 8 may proceed independently once the durable event preconditions are evidenced, but it must not alter public graph APIs or channels.
