# Pre-Gate-6 commit review - 9 September 2026

**Reviewed range:** `dab105a..00b560c` on `refraccion` (mechanical removal of the tracked `.opencode` and virtual-environment trees excluded from application findings).

**Method:** local source/diff review using the ponytail (minimalism/one owner), zero-hallucination (known/inferred/unknown), adversarial review, API-design, security-hardening, and verification disciplines. No external reviewer was installed or used.

## Result: Gate remains BLOCKED

The range correctly prepares the SPA redirect, composite graph index, timestamp investigation, legacy Blade-read-surface retirement, and an authenticated private sensor channel. It does **not** implement the Stage-6 public-graph boundary. The following must remain explicit blockers:

1. **Backend private-channel tests have a known bad expectation.** Laravel `PrivateChannel('sensor.{id}')` exposes the channel name as `private-sensor.{id}`. Any test that asserts `sensor.{id}` for a private channel is objectively wrong and cannot be counted as green evidence.
2. **The timestamp defect reaches the current raw-event pipeline.** `RawReadingNormalizer` can pass `RawSensorEvent.received_at` (cast to `datetime`/Carbon) into `SensorReadingService::createReading()`, while payload timestamps may arrive as raw strings. The issue is therefore a current data-correctness risk, not only a latent overload risk.
3. **Guest alert isolation is incomplete on auth transitions.** `AppLayout.vue` starts global alerts only on initial mount. A guest-to-login transition may not start alerts, while an authenticated-to-guest logout can leave polling/subscriptions alive because the layout normally stays mounted.
4. **Alert realtime can resubscribe after logout.** The `auth:changed` resync path must not call `subscribeAlerts()` when the new state is guest. `stopGlobalAlerts()` must clear the interval, unsubscribe, and clear the authorized alert projection.
5. **Guest alert identity is still rendered without an authorized data source.** `front/src/components/layout/NavBar.vue` renders `Alertas activas: {{ alertsStore.unresolvedCount }}` for guests, while alert config/fetch/subscribe/polling are intentionally blocked. After logout, a stale authenticated count can remain visible. Guests must receive no count/banner identity, and `AlertToast` must not remain visible for guest scope.
6. **Authenticated restricted-sensor realtime is backend-only so far.** `Broadcast::channel('sensor.{sensorId}', ...)` and private delivery flags exist, but the Vue sensor path still listens through the public channel path. Before `PublicGraphVisibility` disables public delivery for restricted sensors, `channelRegistry`/`useSensorRealtime` must support public/private channel selection and auth transitions.
7. **The SPA broadcasting auth flow is not proven.** Tests using `actingAs($user)` do not prove the real Echo/Sanctum bearer-token request to `POST /api/broadcasting/auth`. Add a PAT-backed authorization test for the frontend path.
8. **`NewSensorReading` remains fail-open by default.** A bare `new NewSensorReading($reading)` still defaults to public delivery. Before the Stage-6 cutover, audiences should be explicit or default fail-closed so future callers cannot bypass `PublicGraphVisibility` accidentally.
9. **`/api/config/public` has no clean authenticated replacement yet.** Guest use is guarded, but authenticated alerts still load public config. Add a protected runtime config endpoint with only the alert UI fields normal users need before deleting `/config/public`.
10. **Remaining Blade sensor CRUD creates an auth-continuity risk.** Create/edit/write routes still use web-session auth, then redirect to the SPA. If the SPA only recognizes bearer/localStorage auth, a successful legacy write can land the user in guest state. Either retire the remaining Blade sensor CRUD after API parity, or test and document the session-to-SPA handoff.
11. **Executable docs still contain contradictory old instructions.** Override notes are not enough for agentic execution. Physically remove or rewrite remaining instructions that say Pinia owns subscription release/windowed live projection, that fixed graph limits are frozen, or that broad guest parity includes alert/freshness semantics the backend has not authorized.
12. **Runtime evidence is absent.** This machine has neither PHP/Composer/vendor nor Vitest/node_modules. `php artisan` and unit tests could not run; a Vitest availability check returned false. The MySQL timezone probe, migration/EXPLAIN, Redis delivery tests, backend/frontend unit suites, build, and browser auth-transition traces remain required evidence.

## Required plan corrections

- Treat the SPA redirect, route inventory, index migration, and private channel as **preflight**, not Stage-6 completion.
- Change guest-isolation status from code-complete to **broken on auth transitions** until logout teardown, stale alert clearing, guest DOM, and `AlertToast` behavior are fixed and tested.
- Change authenticated restricted-sensor realtime from code-complete to **partial** until the Vue path consumes private channels for authenticated sensors.
- Change reading-time semantics from latent risk to **current pipeline data-correctness blocker** and extend tests through `RawReadingNormalizer`.
- Change `NewSensorReading` to an explicit-audience/fail-closed contract before visibility cutover.
- Add a protected authenticated runtime-config path before retiring `/api/config/public`.
- Preserve the existing C/mixed timestamp classification and do not publish UTC graph contracts until the real MySQL probe and migration decision close it.
- Keep legacy endpoint removal sequenced after the working graph vertical slice, but make its route-scan a release blocker.

## Fix order to reach PRE-STAGE-6 GATE: PASS

1. `FIX-PRE6-01`: Correct private-channel test expectations to `private-sensor.{id}` and run backend tests.
2. `FIX-PRE6-02`: Extend the time audit to `RawReadingNormalizer`, run SQLite/MySQL probes, and decide canonical UTC write semantics before Stage 6.
3. `FIX-PRE6-03`: Make `AppLayout` react to login/logout with idempotent `startGlobalAlerts()` / `stopGlobalAlerts()`.
4. `FIX-PRE6-04`: Prevent `useAlertsRealtime` from resubscribing when `auth:changed` leaves the app in guest scope; clear authorized alert state.
5. `FIX-PRE6-05`: Remove guest alert counts and guest `AlertToast`; add guest and auth-to-guest DOM tests.
6. `FIX-PRE6-06`: Extend `channelRegistry`/`useSensorRealtime` for public/private sensor channels and migrate authenticated sensor realtime to private.
7. `FIX-PRE6-07`: Make `NewSensorReading` audience-explicit or fail-closed.
8. `FIX-PRE6-08`: Add a protected authenticated runtime config path before deleting `/config/public`.
9. `FIX-PRE6-09`: Retire remaining Blade sensor CRUD or prove the web-session to SPA-auth transition.
10. `FIX-PRE6-10`: Remove contradictory executable instructions and update runtime evidence after the full test matrix runs.

## Verification performed

- `git status --short`: clean before the first documentation update in this review session.
- `git diff --check dab105a..HEAD`: only pre-existing Markdown trailing-whitespace warnings; no application whitespace error.
- Static route, controller, broadcast, migration, frontend, and test inspection completed.
- Test execution blocked by missing local runtimes/dependencies; no pass result is claimed.
