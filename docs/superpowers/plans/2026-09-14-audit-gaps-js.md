# Audit Gaps JS Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the repository-local Gate 9, narrow-mobile UX, frontend test-noise, and documentation gaps identified by the strict audit without representing unavailable live-environment proof as complete.

**Architecture:** Keep authorization server-owned and dashboard personalization administrator-only. Extend the existing mocked Playwright audit runner with an explicit auth-transition interaction; make narrow alert history readable with an alternate presentation; isolate Chart.js canvas behavior in unit tests; then reconcile authoritative status documentation with source and CI configuration.

**Tech Stack:** Vue 3, Vite, Vitest, Playwright audit scripts, CSS, Markdown.

**Spec:** User-approved scope in the 2026-09-14 session: perform items 1–4; do not run downloads, tests, builds, PHP, SQL, Redis, Docker, or browser services; external Gate 10 proof may not be claimed closed.

## Global Constraints

- Work in the existing `refraccion` checkout; do not create a worktree.
- Do not run tests, builds, package installation, Docker, PHP, SQL, Redis, browser servers, or browser automation in this session.
- Author test coverage before production changes but leave execution for an enabled environment.
- Preserve standard-user monitoring and alert-resolution access; dashboard add, remove, reorder, and persistence remain administrator-only.
- Never represent mocked audit results as live CDC or WebSocket proof.
- `PLAN.md` may record current source-backed status and explicit external blockers, but must not close Gate 10 without live evidence.

---

### Task 1: Add mocked browser auth-transition evidence

**Files:**
- Modify: `front/.audit-e2e/run.mjs`
- Modify: `front/.audit-e2e/task10-matrix.txt`
- Create: `front/.audit-e2e/auth-transition.test.js`

**Interfaces:**
- Consumes the existing mocked authentication fixture and role-specific dashboard controls.
- Produces an `auth-transition` interaction artifact with an explicit `pass` boolean and representative mobile/desktop matrix rows.

- [x] Author regression tests for the transition success predicate before runner changes; do not execute them in this session.
- [x] Implement a deterministic transition that validates standard-user monitoring after authentication state is cleared and re-established, while admin-only dashboard controls remain unavailable to the standard role.
- [x] Add representative mobile and desktop rows to the Gate 9 matrix.

### Task 2: Make recent alerts readable at narrow mobile widths

**Files:**
- Modify: `front/src/components/dashboard/SensorMonitorBoard.vue`
- Modify: the stylesheet that owns `.lab-table-wrap` and dashboard responsive rules
- Modify or create: focused dashboard presentation test

**Interfaces:**
- Consumes the existing active-alert records rendered by the dashboard.
- Produces compact alert rows at `<=360px` while retaining the existing table for larger viewports and preserving contained overflow behavior.

- [x] Author a focused presentation test first; do not execute it in this session.
- [x] Render semantic compact rows with time, message, device, and severity for narrow mobile layouts.
- [x] Add scoped responsive CSS without changing alert authorization or data ownership.

### Task 3: Remove known Chart.js canvas noise from unit-test output

**Files:**
- Modify: `front/src/components/charts/SensorReadingChart.test.js`
- Modify: `front/src/components/dashboard/SensorChart.test.js`
- Modify: `front/src/components/sensors/SensorReadingsChart.test.js`
- Create only if needed: a shared test-only chart stub

**Interfaces:**
- Consumes the existing chart view-model props.
- Produces component tests that assert product-owned states without real Canvas rendering under jsdom.

- [x] Author or update test-only chart stubs before test-file changes; do not execute them in this session.
- [x] Retain assertions for loading, partial data, accessibility summary, and adapter props; remove dependence on jsdom Canvas implementation.

### Task 4: Reconcile authoritative status documentation

**Files:**
- Modify: `PLAN.md`
- Modify: `docs/superpowers/plans/2026-09-11-role-access-control.md`
- Modify: `ingestion_service/README.md`

**Interfaces:**
- Consumes repository source/configuration and the current audit matrix design.
- Produces a current-status ledger that distinguishes code coverage, mocked UI evidence, and unperformed live-environment evidence.

- [x] Update base SHA and current source-backed evidence, marking historical counts and stale session narratives as historical.
- [x] Mark completed role-access implementation work accurately while preserving unperformed verification as unverified in this session.
- [x] Correct Compose ingestion-service/volume documentation and record spool capacity, TLS, telemetry, and DLQ-operation requirements as open operational work.

### Task 5: Live-environment evidence assessment

**Files:**
- Modify: `PLAN.md` only if documentation needs to record the result.

**Interfaces:**
- Requires a live Docker topology and Pusher-compatible WebSocket service, which are unavailable in this session.

- [x] Skip execution under the session’s no-Docker/no-runtime constraint. Do not claim the A–E fault injection, 35-second live-WebSocket proof, MySQL EXPLAIN, dependency audits, or branch protection as completed.
