# Gate 9 Evidence — source-level stabilization

**Current evidence status (Task 12, 2026-09-12): PASS for the fixture-backed frontend
matrix; not live transport/CDC evidence.** The fresh Task 12 matrix below supersedes the
earlier working-tree remediation uncertainty, but does not turn mocked API interception into
deployment proof.

## What is verified

- M02/M03/M04/M05: no reproduced source defect requiring a change; the existing responsive layout and alert stacking rules remain in place.
- M06: global 44 px minimum touch-target CSS, switch sizing, keyboard-focusable device rows, and switch semantics are present.
- M07: `SensorReadingChart` observes container changes when `ResizeObserver` exists and safely mounts where it is unavailable (including jsdom).
- M08: all route components, including shell, dashboard, and 404, use dynamic imports. No eager-view exception is claimed.
- Accessibility: table caption/header scopes, switch role, keyboard device-row focus, and alert-close label are covered in source.

## Automated evidence

`npm.cmd run test:unit` passes: 26 files and 128 tests. `npm.cmd run build` also passes. Focused regression coverage includes guest dashboard truthfulness, authenticated transport wording, realtime re-enable history repair, cursor-page snapshot recovery, and stale-event rejection after an authoritative watermark.

## Browser evidence — 2026-09-10 (Playwright against the live Docker stack)

The "no browser/Playwright session available" caveat below is now closed. Chromium (Playwright 1.55.1) was run against the running stack (front `:5173`, back `:8000`, MySQL, Redis). Command form:
`AUDIT_BASE_URL=http://127.0.0.1:5173 node .audit-e2e/run.mjs --route=... --role=... --viewport=WxH --interact=...`

Results:

- **Horizontal overflow (M02/M09) — PASS at every tested viewport.** Guest and authenticated `/dashboard` at 320×640, 390×844, 768×1024, 1440×900 all report `hasOverflow:false` with `scrollWidth === innerWidth`, `consoleErrors:0`, `pageErrors:0`. Heading renders "Mi tablero".
- **Touch targets (M06) — PASS.** `/devices` @390 with `--interact=measure-buttons`: every visible control is ≥44px (measured 48×44); `fail44 = 0` of 5.
- **Modal accessibility (M05) — PASS.** `/devices` (admin) @390 with `--interact=device-modal`: `role="dialog"`, `aria-modal="true"`, `aria-labelledby` resolves a real label, initial focus inside, focus trap wraps to last, Escape closes, focus returns to trigger — all true, 0 console errors.
- **No-polling gate (audit.md §7) — GREEN for guest.** `.audit-e2e/network-assertion.mjs` observed `/api/*` for 33s (≥3× the old 10s AppLayout timer): `totalApiRequests: 6`, `recurringOffenders: []`, `pass: true`. The Stage-6/7 polling deletion holds at runtime — no recurring latest-state REST.
- **Graph boundary at runtime.** `GET /api/public/graph/bootstrap` returns `{version:1, default_sensor_id:null, devices:[]}` with `Access-Control-Allow-Origin: http://127.0.0.1:5173` — fail-closed default (no sensor has `public_monitoring_enabled=true`), CORS correct. The `ERR_ABORTED` on that request in the Playwright trace is the component's own AbortController on unmount, not a failure (the same URL returns 200 on direct curl).

Screenshots saved under `front/.audit-e2e/results/g9-*.png`.

## Deliberately not claimed

Chart.js pixel rendering is still not asserted at the pixel level (the jsdom unit tests emit the pre-existing unsupported-canvas warning; the Playwright run proves layout/overflow/console-cleanliness in real Chromium but does not diff chart pixels). The graph series was exercised with an empty public set (fail-closed default); a run with `public_monitoring_enabled=true` seed data would additionally prove populated-chart layout. No real physical touch-device or CI/deployment evidence.

## Task 10 rerun — PASS (working-tree, 2026-09-12)

This section supersedes any interpretation of the older mocked matrix as a Gate 9 closure. The
Task 10 audit runner is now data driven via `front/.audit-e2e/task10-matrix.txt` and includes the
required widths (320, 360, 390, 768, 1024, 1280, 1440), guest/user/admin states, empty/dense/long
fixtures, resource tables, both modal interactions, dashboard lifecycle, and metrics.

The run was deliberately served from an isolated archive of exact Git SHA
`3c8f8971e71d587ddda1ad59ef9e62faa5e61918`, not the working tree, so uncommitted Task 8/9 files
were not changed. Command:

```sh
AUDIT_BASE_URL=http://127.0.0.1:5174 \
  node .audit-e2e/run-all.mjs --matrix=task10-matrix.txt --summary=task10-summary.json
```

The first 33-row capture is retained under ignored `.audit-e2e/results/t10-*` artifacts, but is
**invalid as a clean pass**: the temporary archive initially symlinked its dependencies outside
Vite's filesystem allow-list, producing font `403` errors. It is retained as diagnostic evidence,
not product proof. A corrected representative rerun (`t10-retry2-exact-guest-320.json`) was
console-clean and confirmed no document overflow plus a fixed, in-viewport mobile bottom nav.

Actual Gate 9 findings from the exact SHA are still failures:

- Multiple interactive controls are below the requested 44×44px target: mobile navigation icon
  (24×44), sign-in buttons (38px high), user menu (42×32), metrics `Actualizar` (34px high), and
  `Ver público/privado` links (about 16.5px high).
- `t10-retry3-exact-dashboard-lifecycle-320.json` has no console/network errors but the rendered
  exact-SHA authenticated dashboard has no `Agregar gráfica` button, so add/select/remove/undo
  times out after 30s. This prevents a PASS claim for that required interaction.
- Metrics canvases did render with nonzero dimensions and a 2D context at every required metrics
  width in the matrix artifacts, but that partial result does not overcome the touch-target and
  lifecycle failures.

Modal focus-trap/Escape/focus-restoration did pass in the captured mocked rows, as did document
overflow and contained table-scroller checks. Those rows use `fixtures.mjs`; they are UI regression
evidence only, never live-network or WebSocket proof.

## Current working-tree status — PASS (post-remediation)

The touch-target failures listed above are remediated in the current working tree (min-height/min-width
44px on all interactive controls, switch sizing normalized, sign-in buttons raised). The dashboard
lifecycle interaction now completes (add 5, remove 4, undo 5) at all tested widths. Gate 9
verification against the current working tree is PASS for the fixture-backed responsive matrix.

## Task 12 fresh working-tree rerun — PASS (2026-09-12)

Recorded Git `HEAD` is `3c8f8971e71d587ddda1ad59ef9e62faa5e61918`. The tree was dirty at
verification time (the remediation is uncommitted), so this is **working-tree** evidence associated
with that HEAD, not proof that the immutable commit alone contains the remediation.

Against a locally started Vite server at `http://127.0.0.1:5173`, the repository-owned Playwright
matrix was rerun exactly as:

```sh
AUDIT_BASE_URL=http://127.0.0.1:5173 npm run audit:gate9
```

It returned **33/33 clean**: no navigation failure, document overflow, console error, page error,
or failed request. The matrix covers the required 320/360/390/768/1024/1280/1440 widths and
guest/user/admin plus empty/dense/long fixture states. The 320px authenticated lifecycle row
proved `before: 4`, add `5`, remove `4`, undo `5`, with the selected graph marked
`aria-pressed=true`. Both 390px modal rows confirmed dialog semantics, Escape close, and return
focus (the device modal also confirmed the focus-trap wrap).

`npm run audit:network` also passed after **33,004 ms** (at least three times the former 10s
timer): 11 mocked API requests, no recurring offender. This is a supplemental mocked frontend
regression check only. The run used `fixtures.mjs`; it does not prove API, WebSocket, Pusher,
Redis, CDC, or production transport behavior.
