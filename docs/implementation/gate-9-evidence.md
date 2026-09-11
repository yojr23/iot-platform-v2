# Gate 9 Evidence — source-level stabilization

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
