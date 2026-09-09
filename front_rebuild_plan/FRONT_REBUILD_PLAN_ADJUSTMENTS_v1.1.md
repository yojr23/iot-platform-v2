# FRONT REBUILD PLAN — Adjustments v1.1

Repository: `yojr23/iot-platform-v2`  
Branch: `refraccion`  
Purpose: align the SINOA Lab Blue Workspace plans with the existing repository, the approved public graph scope, and the current realtime architecture.

> **Decision, 9 September 2026 — guest public graph scope.** This document overrides every conflicting public-data statement in the SINOA implementation plan and its source mockup. Guests receive the same Lab Blue dashboard workspace and graph workflow—responsive shell, toolbar, selectors, current reading, ranges, chart list, main chart, scientific graph details, and an ephemeral chart draft—without a login wall or a second legacy dashboard. Public domain APIs and public realtime channels exist only for approved graph catalog/metadata, bounded graph series, and public sensor-reading updates. They do not expose global metrics, arbitrary inventory/history, alerts, events, device status, preferences, administration, or any restricted sensor data. Restricted dashboard regions have an explicit guest access-required state with no sensitive count, name, severity, value, or timestamp; unsupported mockup controls are omitted rather than inert.

## Mandatory adjustments

1. **Preserve public `/dashboard`.**
   - Guests must continue to view realtime public sensor charts without login.
   - Do not add `requiresAuth` to basic public monitoring.

2. **One dashboard, two capability modes.**
   - Guest and authenticated users share the same dashboard, sensor projections, charts, realtime connection, and responsive component tree.
   - Authentication adds persistence, restricted resources, and privileged actions only.

3. **Public realtime is graph-only P0.**
   - The only public dashboard domain stream is the approved sensor-reading stream, currently compatible with `sensor.{id}`.
   - A server-owned visibility policy must filter the graph bootstrap, graph-series response, and broadcast producer consistently. A restricted sensor never appears in a guest response or emits a public reading payload.
   - `alerts` and `device-status` are not guest channels. If retained for authenticated capabilities, they must use the appropriate authorized transport.
   - Registration is not required for public live graph updates.

4. **Public API surface is graph-only.**
   - Replace the broad public dashboard response with a graph bootstrap containing only permitted device/sensor labels and IDs, unit/precision, public graph thresholds/cadence/quality metadata, and a default graph identity.
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
   - The projection owns request generation/cancellation, bounded ordered merge, de-duplication, freshness, recovery, and subscription release.
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
   - The graph-series contract must accept precise UTC `from`/`to`, constrain window/payload size, declare aggregation, preserve gaps, and return enough metadata for truthful statistics and freshness.

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
