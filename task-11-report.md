# Task 11 report — mobile/accessibility evidence and test hygiene

Date: 2026-09-12

## Outcome

**Partially closed.** The unit-test harness now removes false-positive Vue Router and jsdom
canvas noise from tests that do not exercise routing or chart pixels. The mobile/accessibility
browser gate remains an evidence gap: this session did not produce a fresh authenticated,
long/empty/dense real-browser matrix or real transport proof.

## Changes

- `front/src/components/layout/AppLayout.test.js`: supplies a scoped `useRoute` test stub so
  the component's shell-selection logic is tested without an unmounted router-view.
- `front/src/components/dashboard/SensorMonitorBoard.test.js`: supplies scoped router
  navigation/leave-hook/RouterLink stubs; no production router behavior is replaced.
- `front/src/views/SensorDetailView.test.js`: stubs `SensorReadingsChart` because these tests
  verify live-tail/table projection, not chart rendering. Pixel/canvas behavior remains covered
  by `SensorReadingChart.test.js` and was not mocked.

## Dependency audit evidence

Commands and classification:

```text
npm audit --json
BLOCKED: registry.npmjs.org DNS resolution (getaddrinfo ENOTFOUND); exit 1.

npm audit --offline --json
PASS (cached lockfile audit): 0 info, 0 low, 0 moderate, 0 high, 0 critical;
218 total dependencies (64 prod, 154 dev, 68 optional, 1 peer).

npm ci --offline --ignore-scripts
PASS: 160 packages installed; npm reported 0 vulnerabilities. Node 18 emitted the existing
sass engine warning (sass requires Node >=20.19.0); this is an environment compatibility warning,
not a dependency audit finding.
```

The online audit is **UNVERIFIED/BLOCKED**, not a clean result. The offline result is
**PASS-CACHED** and is retained as the reproducible local artifact for this environment.
No `npm audit fix --force` was run and no dependency versions were changed.

## Verification

```text
npm run test:unit -- src/components/layout/AppLayout.test.js \
  src/components/dashboard/SensorMonitorBoard.test.js \
  src/views/SensorDetailView.test.js
PASS: 3 files, 19 tests.
```

The targeted run has no injection, route-record, or canvas-context errors after the scoped stubs;
the standalone board still emits Vue's unresolved `RouterLink` warning because its compiled
template resolves that symbol outside a router-view. Full-suite verification remains appropriate
before merge; existing pixel tests intentionally retain their real Chart.js path.

## Remaining classification

- M02/M04/M05/M06: **UNVERIFIED** — interaction-capable mobile evidence not rerun here.
- M03/M07: **PARTIAL** — unit chart contracts exist; fresh browser chart-list/live-data proof is
  still required.
- M08: **UNVERIFIED** — accessibility measurements and keyboard flow need a fresh browser run.
- Dependency security: **PASS-CACHED / ONLINE-BLOCKED** as described above.
