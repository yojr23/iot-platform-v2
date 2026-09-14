# Task 10 report — Gate 9 / Gate 10 evidence and CI

Date: 2026-09-12

## Outcome

**Not closed.** This task adds reproducible audit coverage and CI protection, but records real
failures and unavailable infrastructure instead of claiming a green gate.

## Changed files

- `front/.audit-e2e/task10-matrix.txt`: declarative 33-row responsive matrix.
- `front/.audit-e2e/run.mjs`: dashboard add/select/remove/undo interaction and responsive DOM
  inspector (toolbar, controls, mobile expansion, bottom nav, table scrollers, canvas dimensions).
- `front/.audit-e2e/run-all.mjs`: accepts a matrix and separately named summary, avoiding overwrite
  of the baseline audit summary.
- `front/package.json`: `npm run audit:gate9` script.
- `.github/workflows/gate10-quality.yml`: ingestion test job and clearly labelled mocked responsive
  Chromium job with artifacts.
- `docs/implementation/gate-9-evidence.md` and `docs/implementation/gate-10-evidence.md`: current
  evidence, failures, and live-proof gaps.

## Exact-SHA browser evidence

`HEAD` at start: `3c8f8971e71d587ddda1ad59ef9e62faa5e61918`.

To preserve all pre-existing uncommitted Task 8/9 work, the app was served from a temporary
`git archive` of that SHA at `/private/tmp/iot-gate10-head.Yt1cgf`; the browser runner remained in
the workspace only to write ignored `.audit-e2e/results` artifacts. Initial command:

```sh
AUDIT_BASE_URL=http://127.0.0.1:5174 \
  node .audit-e2e/run-all.mjs --matrix=task10-matrix.txt --summary=task10-summary.json
```

The data-driven matrix covers 320/360/390/768/1024/1280/1440, public guest, authenticated user,
admin, empty, normal, dense, long, modal, dashboard lifecycle, resources, and metrics states.
It produced 33 `t10-*` JSON/PNG records. The first complete run is **not a passing artifact**:
Vite rejected font files through the temporary archive's dependency symlink with HTTP 403. The
records are intentionally kept for diagnosis, not cited as clean application output.

After adding an allow-list only in the temporary archive (not in the repository), a representative
exact-SHA row was clean:

```sh
AUDIT_BASE_URL=http://127.0.0.1:5174 node .audit-e2e/run.mjs \
  --route=/dashboard --role=guest --viewport=320x700 --mode=normal \
  --interact=responsive-layout --name=t10-retry2-exact-guest-320
```

It reported `hasOverflow:false`, no console/page/request errors, and an in-viewport fixed mobile
bottom nav. It also measured controls below 44px, including a 24×44 nav icon and 38px-high sign-in
controls. The exact-SHA authenticated lifecycle rerun was console-clean but failed after 30s:

```text
waiting for getByRole('button', { name: 'Agregar gráfica' }).first()
```

Therefore add/select/remove/undo cannot be marked proven for that SHA. Metrics rows did expose
populated Chart.js canvases with nonzero dimensions and a valid 2D context across the required
width matrix. Modal Escape/focus evidence is captured in mocked rows only.

## Browser-control provenance

The available in-app Browser integration was attempted first. Its required plugin module was
missing at:

```text
/Users/j.rinconc/.codex/plugins/cache/openai-bundled/browser/26.730.61639/skills/
control-in-app-browser/scripts/browser-client.mjs
```

The task explicitly permits existing audit scripts and real Chromium, so the repository's declared
Playwright Chromium runner was used as the fallback. This is real Chromium rendering, but fixture
rows remain mocked API evidence.

## Gate 10 live evidence

Attempted availability command:

```sh
docker compose ps --format json
```

Actual output:

```text
Cannot connect to the Docker daemon at unix:///Users/j.rinconc/.docker/run/docker.sock.
Is the docker daemon running?
```

No real Pusher-compatible WebSocket, API, Redis, Debezium, or CDC consumer stack was available.
Consequently `npm run audit:network:live` (including its >=35s observation), guest/public versus
authenticated/private network proof, reconnect dedup, logout leakage, and CDC fault scenarios
A–E were not run. This is a live-evidence GAP, not a test pass or a mock substitute.

## CI configuration validation

Passed locally:

```sh
node --check front/.audit-e2e/run.mjs
node --check front/.audit-e2e/run-all.mjs
node -e "JSON.parse(require('fs').readFileSync('front/package.json'))"
ruby -e "require 'yaml'; YAML.load_file('.github/workflows/gate10-quality.yml')"
git diff --check
```

The added `ingestion` job follows the requested exact steps. The separate
`frontend-responsive-e2e-mocked` job uses fixture-backed UI checks and labels them explicitly;
it cannot provide real transport or CDC proof. Neither job has been observed running on GitHub
in this local session. `npm run audit:no-polling:source` passed locally. A direct local
`python3 -m pytest -q` could not start because this machine's Python 3.11 environment has no
installed `pytest`; `pytest` is present in `ingestion_service/requirements.txt`, so the added CI
job installs it before invoking the required command.

## Required follow-up before closure

1. Fix the recorded touch targets and add the expected dashboard lifecycle control on the target
   SHA, then rerun the matrix from a clean exact checkout.
2. Start the Docker deployment including a real Pusher-compatible endpoint and supply dedicated
   E2E credentials; run `npm run audit:network:live` with its normal >=35s windows.
3. Execute and retain logs for CDC scenarios A–E against the real stack, including durable outbox,
   PEL/XAUTOCLAIM, Redis recovery, DLQ, and restart assertions.
4. Confirm the two added CI jobs green on a push/PR.
