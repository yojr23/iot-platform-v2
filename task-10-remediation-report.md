# Task 10 remediation report — Gate 9 product failures

Date: 2026-09-12

## Outcome

The reproducible Gate 9 dashboard lifecycle and touch-target failures are remediated in the
working tree. Nothing was staged, committed, pushed, or reset. This report concerns rendered UI
evidence using the repository's fixture-backed audit harness; it is not evidence for a live API,
WebSocket, or CDC deployment.

## Root cause and fix

- `useLabWorkspace` incorrectly treated a user's personal dashboard layout as an admin capability.
  Dashboard editing is now available for temporary guest drafts and ordinary authenticated users;
  `save()` still requires authentication and the server remains the authorization boundary.
- The audit lifecycle selected fixture sensor `2/202`, which was already one of the four default
  widgets. The runner now selects the final fixture device's first sensor, so it proves a real
  add instead of a no-op selection.
- Actual controls, rather than decorative containers, now have 44px minimum targets: dashboard
  buttons/selects/ranges/edit controls, mobile menu/account, demo toggle, resource actions/status
  control, and modal close actions. The resource CSS no longer overrides the global button target
  with 34px/36px values.
- The browser fixture now implements the graph bootstrap/catalog/series contracts consumed by the
  current dashboard, so the lifecycle is tested with populated rendered widgets.

## Chromium evidence

The in-app Browser runtime was attempted first and returned `No browser is available`. The task
permits the repository's real Playwright Chromium runner, which was used against a local Vite app
at `http://127.0.0.1:5173`. API requests were intercepted only by the repository audit fixture;
the UI actions are real rendered browser interactions.

| Artifact | Result |
| --- | --- |
| `t10r-dashboard-user-lifecycle-320.json` | 320×700: `before:4`, add → `5`, selected item has `aria-pressed=true`, remove → `4`, undo → `5`; `pass:true`; no overflow, console/page/request errors. |
| `t10r-dashboard-user-390.json` | 390×844: no overflow; `undersizedControls:[]`; fixed mobile bottom navigation is in viewport; populated canvas has a 2D context; no browser errors. |
| `t10r-dashboard-user-768.json` | 768×1024: no overflow; `undersizedControls:[]`; all toolbar actions are 44px high; populated canvas has a 2D context; no browser errors. |
| `t10r-dashboard-admin-1440.json` | 1440×900: no overflow; `undersizedControls:[]`; toolbar controls are 44px high; populated canvas has a 2D context; no browser errors. |
| `t10r-metrics-admin-320.json` | 320×700 metrics: no overflow; the previously 34px `Actualizar` target is now clear of the 44px audit; populated canvases render; no browser errors. |

Artifacts and screenshots are retained under `front/.audit-e2e/results/` with the `t10r-` prefix.

## Verification

- `npm run test:unit` — passed (39 test files, 199 tests).
- `npm run build` — passed.
- `npm run audit:no-polling:source` — passed.
- `node --check .audit-e2e/run.mjs` and `git diff --check` — passed.

The unit run continues to print the pre-existing jsdom/Chart.js unsupported-canvas diagnostics;
the real Chromium checks above confirm a valid 2D context and populated canvases without browser
console or page errors.

## Remaining scope boundary

This remediation does not close the separate Task 10 live-infrastructure gaps: no real deployed
Pusher-compatible transport, API/Redis/CDC stack, or browser authentication credentials were
available. No polling was added and no browser assertion was weakened.
