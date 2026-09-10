# Gate 9 Evidence — source-level stabilization

## What is verified

- M02/M03/M04/M05: no reproduced source defect requiring a change; the existing responsive layout and alert stacking rules remain in place.
- M06: global 44 px minimum touch-target CSS, switch sizing, keyboard-focusable device rows, and switch semantics are present.
- M07: `SensorReadingChart` observes container changes when `ResizeObserver` exists and safely mounts where it is unavailable (including jsdom).
- M08: all route components, including shell, dashboard, and 404, use dynamic imports. No eager-view exception is claimed.
- Accessibility: table caption/header scopes, switch role, keyboard device-row focus, and alert-close label are covered in source.

## Automated evidence

`npm.cmd run test:unit` passes: 26 files and 128 tests. `npm.cmd run build` also passes. Focused regression coverage includes guest dashboard truthfulness, authenticated transport wording, realtime re-enable history repair, cursor-page snapshot recovery, and stale-event rejection after an authoritative watermark.

## Deliberately not claimed

This is not formal Gate 9 completion evidence. No browser/Playwright session, viewport screenshots, real touch-device measurement, lazy-chunk network trace, PHP integration run, Redis run, or deployment/CI evidence was available on this machine. The jsdom chart tests still emit the pre-existing unsupported-canvas warning; they pass but do not prove Chart.js rendering in a browser.
