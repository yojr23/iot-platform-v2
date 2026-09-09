# FRONT REBUILD PLAN — Adjustments v1.1

Repository: `yojr23/iot-platform-v2`  
Branch: `refraccion`  
Purpose: align the SINOA Lab Blue Workspace plans with the existing public realtime dashboard and the current realtime architecture.

## Mandatory adjustments

1. **Preserve public `/dashboard`.**
   - Guests must continue to view realtime public sensor charts without login.
   - Do not add `requiresAuth` to basic public monitoring.

2. **One dashboard, two capability modes.**
   - Guest and authenticated users share the same dashboard, sensor projections, charts, realtime connection, and responsive component tree.
   - Authentication adds persistence, restricted resources, and privileged actions only.

3. **Public realtime is P0.**
   - Public-safe telemetry uses the existing Echo/Pusher-compatible channels (`sensor.{id}`, `alerts`, `device-status`).
   - Registration is not required for public live updates.

4. **No frontend polling or polling fallback.**
   - Initial bounded REST hydration is allowed.
   - Reconnect/visibility/auth lifecycle can trigger one bounded recovery snapshot.
   - Steady-state updates come from Echo/WebSocket.

5. **Reuse current owners instead of creating a parallel frontend.**
   - `front/src/views/DashboardView.vue`
   - `front/src/components/dashboard/*`
   - `front/src/realtime/echo.js`
   - `front/src/realtime/channelRegistry.js`
   - `front/src/realtime/useSensorRealtime.js`
   - `front/src/realtime/useAlertsRealtime.js`
   - `front/src/stores/alerts.js`
   - `front/src/stores/auth.js`
   - existing API adapters.

6. **Gate 6 supplies the sensor projection foundation.**
   - The rebuild must consume the shared Pinia sensor-reading projection.
   - The current `SensorMonitorBoard` 2-second polling loop is transitional and must be removed during the realtime cutover.

7. **Clarify Pinia guidance.**
   - Do not add another state-management framework.
   - Pinia is appropriate for shared cross-component domain projections such as sensor readings and alerts.
   - Transient UI state stays local/composable.

8. **Guest workspace behavior.**
   - Guests may add/remove/reorder/select charts in an ephemeral draft.
   - Guest Save opens authentication/persistence flow; it must not block live monitoring.
   - Do not display `Saved` without server confirmation.

9. **Authenticated workspace behavior.**
   - Use the existing dashboard preference owner.
   - Extend it rather than creating a second preferences API.
   - Rich revision/schema/conflict semantics remain a backend extension where not yet supported.

10. **Server-owned public scope.**
    - Vue must not be the security boundary.
    - Public listing/history endpoints and public broadcast payloads expose only data explicitly approved for public monitoring.
    - Restricted sensors/resources require authenticated authorization.

11. **Time-range implementation must use real backend contracts.**
    - RF03 ranges `1m`, `5m`, `1h`, `6h`, `24h` cannot be simulated by arbitrary client-side sample counts.
    - Map them to the existing reading/history API with explicit `from/to`, timezone and aggregation semantics as supported.

12. **Add guest-specific verification.**
    - No-token realtime chart update.
    - No recurring latest-reading GETs after hydration.
    - Restricted sensor not exposed to guest.
    - Login/logout does not duplicate subscriptions.
    - Guest draft survives auth transition where practical.

## Updated execution order

```text
verify current SHA / owners
→ preserve public route and public realtime contract
→ Gate 6 shared sensor-reading Pinia projection
→ remove sensor polling
→ SINOA tokens + responsive Lab Blue shell
→ public realtime sensor vertical slice
→ scientific stats / ranges / inspector
→ guest workspace editing
→ authenticated persistence enhancement
→ public-safe alerts/events + authorized extensions
→ mobile/accessibility
→ verification and rollout
```

## Architecture invariant

```text
PUBLIC REST HYDRATION ───────┐
                             ▼
PUBLIC ECHO CHANNELS → SENSOR/ALERT PINIA PROJECTIONS → LAB BLUE WORKSPACE
                             ▲
AUTHENTICATED APIs ──────────┘
        (preferences / restricted capabilities)

Guest and authenticated users do not have separate telemetry implementations.
```
