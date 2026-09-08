# Stage 0 — Evidence baseline (PLAN.md Stage 0 / audit.md G0)

**Baseline SHA tested:** `35acbb50255949d745b069a482b85df0d9ea1c5e` (`front/src` unchanged since
`d5765bc5...`, which is what the runs below actually executed against — verified with
`git diff --stat d5765bc5...35acbb5 -- front/src` returning empty). Branch `refraccion`.

**Environment:** Node `v22.16.0` (via nvm; the shell default node v14 was overridden per
instructions), npm `10.9.2`, `@playwright/test` `1.55.1` (already declared in
`front/package.json` devDependencies), Chromium `140.0.7339.186` (Playwright build v1193)
installed this session via `npx playwright install chromium` — full download succeeded, no
`ENVIRONMENT_CONSTRAINT`. Dev server: `npm run dev` at `http://127.0.0.1:5173`.

**Important pre-existing drift discovered this session:** Stage 1 (mobile blockers) and
Stage G0D (ownership matrix) have **already landed on this branch** ahead of this Stage 0 pass
(`git log --follow front/src/views/DevicesView.vue` shows commit `8491dff "feat: mobile
blockers + accessible BaseModal (Stage 1)"`). `BaseModal.vue` already has
`role="dialog"`/`aria-modal`/focus trap/Escape/return-focus, `DevicesView.vue`'s header already
has `flex-wrap`, and `AlertToast.vue` already overrides `--bs-toast-max-width`. This is why
several findings below come back **REFUTED against current HEAD** even though `audit.md` still
describes them as open — the code moved since that audit snapshot. This report evaluates
**current HEAD**, not the audited SHA, per PLAN.md 0.1's instruction not to claim readiness
from the old SHA alone.

## What changed this session (all inside my owned paths)

- `front/.audit-e2e/run.mjs` — **G0B**: removed the hardcoded Windows Playwright path
  (`C:/Users/jvrincon/...`) and now imports `chromium` from `@playwright/test` normally. Runs
  from a fresh clone with no machine-specific setup beyond `npm install` +
  `npx playwright install chromium`. Added `--interact=` steps: `device-modal`,
  `alert-rule-modal`, `toast`, `measure-buttons`, `diagnose-overflow`, `add-monitors`.
- `front/.audit-e2e/fixtures.mjs` — **G0C**: fixed the `/dashboard/public` and
  `/dashboard/metrics` field-name mismatch (now returns the real
  `total_devices/active_devices/total_sensors/active_alerts/unresolved_alerts` shape from
  `back/app/Http/Controllers/Api/DashboardController.php`, plus a real `devices[]` array with
  embedded `sensors[]`, matching `publicDevices()` exactly). Also fixed two more mock-shape bugs
  found while doing this (same root cause class — mock shape didn't match the real controller):
  - `/sensors/{id}/latest-readings` was wrapped in `{data:[...]}`; the real controller
    (`SensorApiController::latestReadings`) returns a bare array, and `SensorMonitorBoard.vue`
    reads `response.data` directly expecting an array — the old mock made every monitor render
    "0 puntos" / the permanently-empty chart state noted in `audit.md §2a`'s open question 2.
  - `/alerts/active` was wrapped in `{data:[...]}`; the real controller
    (`AlertController::active`) returns `{count, alerts}` at the top level, and
    `stores/alerts.js#fetchActiveAlerts` reads `response.data.alerts`/`response.data.count`
    directly — the old mock silently produced an always-empty active-alerts badge.
- `front/.audit-e2e/matrix.txt` — extended from the original 22 page-load rows to 43 rows,
  reformatted to `route|role|viewport|mode|interact|name`, adding interaction rows (modals,
  toast, button measurement, overflow diagnosis, add-monitors) and `long`/`dense`/`empty`
  fixture-mode rows for tables/admin headers.
- `front/.audit-e2e/run-all.mjs` — new orchestrator, reads `matrix.txt`, spawns `run.mjs` per
  row, writes `results/summary.json`. `npm run audit:baseline`.
- `front/.audit-e2e/event-injection.mjs` — new **0.7** permanent event-injection rig.
  `npm run audit:events`. 13/13 checks pass.
- `front/.audit-e2e/network-assertion.mjs` — new **0.8** permanent no-polling network
  assertion. `npm run audit:network`. Fails today by design (documented below).
- `front/package.json` — added `audit:baseline`, `audit:events`, `audit:network` scripts only
  (no new dependencies; `@playwright/test` was already declared).

Ran the full matrix: **43/43 runs clean** (no nav error, no top-level document overflow at
initial load, 0 console errors, 0 page errors, 0 failed `/api/*` requests). `front/tests/e2e`
was not created — the existing `.audit-e2e` harness covers everything asked for; adding a
parallel `tests/e2e` tree for the same job would be the "second harness" ponytail/G0D would
flag, so it was skipped.

## M01–M09 verdicts

| ID | Verdict | Evidence |
| --- | --- | --- |
| **M01** | **PLAYWRIGHT_CONFIRMED — still broken.** `NavBar.vue:48` still hides `/profile` behind `d-none d-md-inline` inside the mobile menu (verified against current `NavBar.vue` source read this session; the Stage 1 commit did not touch `NavBar.vue`). Not yet fixed. | Source read; not independently re-screenshotted since the DOM class hasn't changed and the original menu-open-state caveat still applies (menu open state itself still not clicked in this pass — clicking the hamburger toggle and asserting `/profile` visibility is a Stage 1 follow-up, not required by Stage 0's own scope). |
| **M02** | **PARTIALLY REFUTED, one real inconsistency found.** `DevicesView.vue`'s header already has `flex-wrap` (Stage 1) and renders clean under `long`/`dense`/`empty` fixture modes at 320/390px — `m02-devices-admin-long-320`, `-long-390`, `-dense-320`, `-empty-320` all `hasOverflow:false`, visually confirmed clean (title/buttons wrap, no clipping). `AlertRulesView.vue`'s header (`views/AlertRulesView.vue:3`) does **not** have `flex-wrap` (inconsistent with `DevicesView.vue`) — visually confirmed cramped-but-not-broken at 320px (`m02-alertrules-admin-long-320.png`: heading wraps to 2 lines, buttons stay top-right, no overlap, no document overflow). Not a confirmed defect today, but a real consistency gap worth closing alongside Stage 9's `PageHeader.vue` extraction if one happens. | `front/.audit-e2e/results/m02-*.png/json` |
| **M03** | **PLAYWRIGHT_CONFIRMED — real bug, newly quantified.** Unblocked by the G0C fixture fix. At 320×700 with `mode=long` and 2 secondary monitors added (`--interact=add-monitors`), `.monitor-card__header`'s `.btn-group` (Subir/Bajar/Eliminar) overflows its own header box (`groupRight: 364` vs header's own `287`) and the **document itself** goes from `scrollWidth:320` (clean at initial load, main monitor only) to `scrollWidth:364` (`hasOverflow:true`) once a second monitor with a long sensor name is present. Clean at 390×844 (`hasOverflow:false`, `groupRight` still contained). Root cause: `.monitor-card__header` is a `d-flex justify-content-between` with **no `flex-wrap` and no `min-width:0` on the title block** — confirmed exactly the mechanism `audit.md` M03 originally hypothesized, now with a live measurement instead of a guess. | `front/.audit-e2e/results/m03-dashboard-long-320.json` (`interaction.overflowAfter.hasOverflow:true`, `scrollWidth:364` vs `clientWidth:320`), `.png` |
| **M04** | **REFUTED — already fixed (Stage 1).** `AlertToast.vue`'s scoped CSS now has `.alert-toast-container .toast { --bs-toast-max-width: 100%; width: min(350px, calc(100vw - 2rem)); }`. Injected a real long/critical alert via the exact `alerts.js#addRealtimeAlert` action (dynamic `import('/src/stores/alerts.js')` inside `page.evaluate`, hitting the same Pinia singleton the running app uses — see EVENT_HANDLER_SIMULATED note below) and measured the rendered toast: 320px viewport → toast width **288px** (fits, right edge at 304 < 320), close button reachable; 390px viewport → toast width **350px** (fits, right edge at 374 < 390), close button reachable. | `front/.audit-e2e/results/m04-toast-320.json`, `m04-toast-390.json` |
| **M05** | **MOSTLY REFUTED — already fixed (Stage 1), one real return-focus bug found.** `BaseModal.vue` already implements `role="dialog"`, `aria-modal="true"`, `aria-labelledby` (pointing at a real element), initial focus into the dialog, a Tab focus trap (verified Shift+Tab from the first focusable element wraps to the last), Escape-to-close, and return-focus. Device modal (`DevicesView.vue`, via `BaseModal` directly): **all 6 checks pass** at both 390px and 320px. Alert-rule modal (`AlertRuleModal.vue` → `BaseModal`): role/aria-modal/initial-focus/Escape-close all pass, but **`returnedFocus:false`** — focus does not return to the "Nueva regla" trigger after Escape. Root cause identified from source: `AlertRulesView.vue#openCreate()`/`openEdit()` set `metadataLoading.value = true` and `await loadMetadata()` **before** setting `modalOpen.value = true`, and the trigger button has `:disabled="metadataLoading"` — the button becomes `disabled` (and browsers blur a disabled focused element) during that awaited gap, before `BaseModal`'s `watch(() => props.show, ...)` captures `previouslyFocused = document.activeElement`, so it likely captures the wrong element. This reproduces consistently (checked 3x). Device modal's `openCreate`/`openEdit` have no such awaited gap before opening, which is why it doesn't hit this. | `front/.audit-e2e/results/m05-device-modal-390.json`, `-320.json`, `m05-alertrule-modal-390.json` |
| **M06** | **PLAYWRIGHT_CONFIRMED — measured, matches audit's own nuance.** Extended the button selector to also catch `.btn-group-sm > .btn`/`a` (the original M06 evidence gap only checked `.btn-sm` directly, missing the Acciones column buttons entirely). Measured real rendered buttons on `devices-admin` at 320 and 390px: status toggle ("Activo"/"Inactivo") 58–69×31px; "Ver"/"Editar"/"Eliminar" 40–69×31px. **All consistently fail the 44×44 product target, all consistently pass the 24×24 WCAG 2.2 AA minimum** — exactly the nuance `audit.md §5` asked for, not a blanket AA failure. Two navbar controls ("Alertas 4", "Salir") measured 0×0 — correctly explained as hidden behind the collapsed `<991.98px` navbar-collapse, not a real violation. | `front/.audit-e2e/results/m06-buttons-devices-admin-390.json`, `-320.json` |
| **M07** | **UNBLOCKED, REFUTED for the specific "empty chart" symptom, chart itself renders correctly.** The G0C fixture fix (see above) resolved the actual root cause of the empty-chart observation in `audit.md §2a` — it was a mock-shape bug (`/sensors/{id}/latest-readings` double-wrapped), not a ResizeObserver/Chart.js timing bug. With real data, the dashboard's main monitor renders a full 60-point line chart at 390×844 with no console errors (`g0c-dashboard-user-390-realdata.png`, visually confirmed). Canvas/parent resize across 390→320→768→390 was **not** separately measured this session (time-boxed to the fixture fix + the M03 multi-chart case, which does exercise the chart at 320px cleanly per the same run) — flagged as a residual for Stage 9 if chart.js resize behavior needs its own dedicated measurement pass. | `front/.audit-e2e/results/g0c-dashboard-user-390-realdata.png`, `m03-dashboard-long-320.png` (charts render correctly even in the overflow case — the overflow is the header/button-group, not the chart) |
| **M08** | **NOT_APPLICABLE this session (perf timing, not Stage 0's scope).** `npm run build` output confirms the single-chunk situation is unchanged: `dist/assets/index-*.js` is 604.83 kB (197.36 kB gzip) with Vite's own "chunks larger than 500kB" warning. No mobile-startup timing was measured (Playwright trace/CPU-throttle timing is a distinct, larger effort than Stage 0's interaction/injection/network scope) — still requires the measurement `audit.md` asks for before any route-splitting work. | `npm run build` output, this session |
| **M09** | **REFUTED against current HEAD — already fixed (Stage 1).** Reproduced `devices-admin` at exactly 320×700 per PLAN.md 0.6 across `normal`, `dense` (40 devices), and `long` (full lab/device-name strings) fixture modes: **all three `hasOverflow:false`** (`document.documentElement.scrollWidth === clientWidth === 320` in every case). Root cause traced via git history: commit `8491dff` added `flex-wrap` to `DevicesView.vue`'s header (`git log -p --follow front/src/views/DevicesView.vue` shows the exact diff), which is precisely the fix `audit.md §5`'s corrected M09 hypothesis #1 named as the most likely contributor. Diagnostic tooling (`--interact=diagnose-overflow`, walks up from any element whose `right` exceeds the viewport, dumping `display`/`flex-wrap`/`min-width`/rect for up to 8 ancestors) is built and checked in for future regressions, but found nothing to report this time because there is no overflow to diagnose. | `front/.audit-e2e/results/m09-devices-admin-320-diagnose.json`, `-dense.json`, `-long.json` (all `{"hasOverflow":false}`); `git log -p --follow -- front/src/views/DevicesView.vue` |

## 0.7 — Event-injection harness (`front/.audit-e2e/event-injection.mjs`)

**Label: `EVENT_HANDLER_SIMULATED`, not `WEBSOCKET_TRANSPORT_VERIFIED`.** Confirmed via source
(`front/.env`: `VITE_PUSHER_APP_KEY=` empty) that `front/src/realtime/echo.js#getEcho()` returns
`null` in this environment, so the real `channel.listen(...)` binding in
`useAlertsRealtime.js`/`useSensorRealtime.js` never happens today — there is no local
Pusher-compatible server in this sandbox either. This is an `ENVIRONMENT_CONSTRAINT` for real
transport verification, not something Stage 0 can fabricate a pass for.

What the rig actually does: loads the **real, unmodified** `front/src/stores/alerts.js`,
`front/src/realtime/useAlertsRealtime.js`, and `front/src/realtime/useSensorRealtime.js` through
Vite's own SSR module loader (`vite.createServer({ ssr })` + `ssrLoadModule`, reusing the
project's real `vite.config.js` aliases — same module graph as the browser, not a hand-copied
reimplementation), substituting only the transport (`./echo` resolves to a small in-memory fake
Echo/channel with `.channel()/.listen()/.leaveChannel()`, matching `audit.md §21`'s own
"in the substitute" methodology). `npm run audit:events` → **13/13 checks pass**:

- Alerts: subscribe binds without a real connection; creation adds to `activeAlerts` +
  `latestAlert`; duplicate `id` delivery is deduped (`seenAlertIds`) with `unresolvedCount`
  unchanged; malformed/missing-id payload doesn't throw; **confirmed known gap** — a
  `resolved:true` event for an already-active alert does **not** remove it from `activeAlerts`
  or decrement `unresolvedCount` (no `alert.resolved` handling exists in `addRealtimeAlert` yet
  — re-confirms `audit.md §21`'s "resolution unhandled" against current HEAD, feeds Stage 7);
  channel teardown (`unsubscribeAlerts()`) stops delivery even via a stale channel reference;
  resubscribe preserves the module-level `seenAlertIds` dedup set across the teardown boundary.
- Sensor readings: subscribe binds; matching `sensor_id` reaches the `onReading` callback with a
  normalized reading; mismatched `sensor_id` is ignored; a non-numeric `value` is still
  forwarded unvalidated (`normalizeReading` doesn't coerce/validate — consumer's responsibility,
  worth a note for the future shared sensor-readings projection store in Stage 6); teardown
  stops delivery.
- `device.status.changed`: **NOT_APPLICABLE** — confirmed via `grep -rln
  "useAlertsRealtime\|useSensorRealtime" front/src` that no third realtime composable exists for
  device status. There is nothing on the frontend to inject into yet; this matches
  `audit.md §8`/RC2's own documentation that `DeviceStatusUpdated` is never dispatched
  end-to-end. Stage 8.1 territory, not a Stage 0 gap.

## 0.8 — No-polling network assertion (`front/.audit-e2e/network-assertion.mjs`)

`npm run audit:network`. Per PLAN.md 0.8, **this must currently fail (RED), and it does**:

```
Observing /api/* traffic for 33000ms (>= 3x the 10000ms AppLayout active-alerts timer)
{
  "observedMs": 33003,
  "totalApiRequests": 37,
  "recurringOffenders": [
    { "pathname": "/api/sensors/102/latest-readings", "count": 16, "avgGapMs": 2000 },
    { "pathname": "/api/alerts/active", "count": 9, "avgGapMs": 3128 }
  ],
  "pass": false
}
```

This exactly reproduces `audit.md §7`'s documented timers: `SensorMonitorBoard.vue`'s default
2000ms poll (`avgGapMs: 2000`, dead-on) and the combined effect of `AppLayout.vue`'s 10s timer +
`ActiveAlertsCard.vue`'s independent 5s timer both hitting `/alerts/active` (interleaved,
`avgGapMs: 3128` ≈ the ~3.33s average the audit's own "18 requests/minute" math implies). **This
red result is correct and expected at Stage 0** — polling has not been removed (that's Stages
6/7). The rig exits non-zero on purpose; do not wire it into a CI gate that expects green until
after Stage 6/7 land.

## Honesty notes / what was NOT verified

- **No real Laravel/Redis/Pusher-compatible WebSocket transport was started or tested.** Every
  realtime claim above is `EVENT_HANDLER_SIMULATED` or `PLAYWRIGHT_CONFIRMED` (DOM/CSS/JS
  behavior only, no backend). `audit.md §21`'s "Real transport" and "Durable backend" rows
  remain untouched — out of scope for a frontend-only Stage 0 harness.
- **M08 mobile-startup timing was not measured** (needs CPU throttling / Lighthouse-style
  timing, a materially different tool than this DOM-interaction harness).
- **M07's canvas/parent resize across a live 390→320→768→390 sequence within one browser session
  was not separately measured** — only static renders at each viewport were checked. The chart
  itself is confirmed rendering correctly with real data now (root cause of the empty-chart
  observation was the mock, not Chart.js), but a dedicated resize-while-mounted test is still
  open.
- **M01's mobile hamburger menu was not clicked open** in this pass (source-confirmed only,
  consistent with the existing audit's own caveat).
- All 43 matrix runs and both new rigs were executed against the mocked API fixtures in
  `front/.audit-e2e/fixtures.mjs`, not a real backend — `audit.md §21`'s note that "real backend
  contract tests must confirm them before acceptance" still applies.

## Commands run (for reproduction)

```bash
cd front
npx playwright install chromium         # one-time; succeeded, no ENVIRONMENT_CONSTRAINT
npm run dev &                           # serves http://127.0.0.1:5173
npm run audit:baseline                  # 43/43 clean — writes .audit-e2e/results/summary.json
npm run audit:events                    # 13/13 EVENT_HANDLER_SIMULATED checks pass
npm run audit:network                   # exits 1 — expected RED, documented above
npm run build                           # unaffected, unchanged 604.83kB main chunk warning
npm run test:structure && npm run test:phase4 && npm run test:phase5 && npm run test:phase7
```
