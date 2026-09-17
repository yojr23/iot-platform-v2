# Simulated infra certification — dry run

**SIMULATED CERTIFICATION — NOT REAL EVIDENCE. Generated on Windows without the live stack. Every result below is an EXPECTED outcome to be verified on Mac, not an observed pass. Generated 2026-09-17.**

This document is a dry-run checklist for the infra gates that cannot be run on
Windows — they need the full Docker stack (MySQL, Redis, Debezium, an MQTT
broker, a real browser) that only the Mac session has exercised so far. Real,
observed evidence for what *has* actually run lives in
`docs/mac-certification-evidence.md`; read that first for tone/structure —
this document only covers the gates that file still lists as OPEN.

## Gate checklist

### 1. Full MQTT→browser vertical

- **Certifies:** raw device event flows MQTT broker → Python spool → HTTP ingestion → outbox → Debezium CDC relay → `iot.raw-events` → raw consumer → `sensor_readings` → domain outbox → CDC → `iot.domain-events` → broadcaster → browser, end to end, with a live MQTT broker in the loop (the current live evidence stops at HTTP ingestion + raw-consumer tier).
- **Harness (Mac):** bring up `docker compose --profile workers up -d` plus an MQTT broker service, publish a marked reading via the Python ingestion simulator (`script_datos.py` or the ingestion service's MQTT client) against a real device/sensor mapping, and trace the event through each Redis stream (`XRANGE iot.raw-events`, `XRANGE iot.domain-events`) to a browser-visible update.
- **EXPECTED result:** the reading reaches the browser's live sensor projection within the recovery window, with the same 8-stage trace already proven from HTTP ingestion (M11 in `docs/GATE10_MAC_VERIFICATION_EVIDENCE_2026-09-16.md`), now with the MQTT front end included.

### 2. Redis/Debezium restart + XAUTOCLAIM worker-takeover + broadcast crash windows

- **Certifies:** the event pipeline survives infra restarts without losing or duplicating events, and a killed consumer's pending entries are reclaimed by a peer.
- **Harness (Mac):** with the stack up and events flowing, `docker compose restart redis`, then `docker compose restart debezium`, confirming stream/offset survival; separately, kill a `raw-consumer`/`domain-event-consumer` worker mid-processing and confirm `XAUTOCLAIM` on `raw-process-v1` (or the domain equivalent) reclaims the idle pending entry on the surviving worker; kill the broadcaster mid-delivery and confirm no event is lost or double-delivered on restart.
- **EXPECTED result:** Redis restart — streams and consumer-group offsets persist (AOF/RDB as configured), no data loss. Debezium restart — resumes from its committed offset, no re-snapshot, no duplicate CDC emission. Worker kill — the peer consumer's `XAUTOCLAIM` picks up the orphaned pending entry after the idle threshold and completes it exactly once. Broadcast crash window — event stays durable at `pending`/`delivered_at=null` until a consumer completes delivery; no silent loss.

### 3. Live 60s no-polling browser capture

- **Certifies:** a real browser session, connected to the live stack for at least 60 seconds, issues zero periodic/polling network requests for state that should be event-driven (sensor readings, alerts, device status).
- **Harness (Mac):** `front/.audit-e2e/network-assertion-live.mjs` (or the live variant of the no-polling harness referenced in the 2026-09-17 RECONCILIATION section) against the running `front`/`back` stack, authenticated session, ≥60s capture window, asserting zero recurring `GET` calls to `latest-readings`/`alerts/active`/device-status endpoints outside of lifecycle-triggered recovery.
- **EXPECTED result:** PASS — zero periodic requests observed; only lifecycle-triggered (subscribe/reconnect/visibility/auth) requests appear, consistent with the harness-fixed but not-yet-run status recorded in the current RECONCILIATION section (harness bug fixed: `apiLogin` now reads `access_token`; live 60s run itself is still pending).

### 4. Real-device desktop/mobile QA (Phase L)

- **Certifies:** the responsive/mobile UI (already passing on a mocked Playwright matrix) behaves correctly on real desktop and mobile hardware/browsers, not just a simulated viewport.
- **Harness (Mac):** manual or Playwright-on-real-device pass across the DoD viewport set (320/360/390/768/1024/1280/1440) on at least one real mobile device (iOS Safari and/or Android Chrome) and one real desktop browser, covering the same interaction set as the mocked matrix (modals, toasts, device/sensor tables, chart controls).
- **EXPECTED result:** parity with the mocked matrix — no real-device-only defect (touch target misses, viewport quirks, orientation-change bugs) that the mock couldn't catch.

### 5. Branch protection (Phase Q)

- **Certifies:** `main` (and/or `refraccion` if treated as a protected integration branch) rejects direct pushes and requires the CI gate (`gate10-quality.yml`) to pass before merge.
- **Harness (Mac or any machine with GitHub admin):** GitHub repo Settings → Branches → branch protection rule requiring status checks (`gate10-quality` jobs) and disallowing force-push/direct-push; verify by attempting a direct push or an unprotected PR merge.
- **EXPECTED result:** direct push to the protected branch is rejected; PR merge is blocked until required status checks pass. This gate needs GitHub admin rights and cannot be performed from a working tree on any machine, Windows or Mac.

## Closing note

Gate 9, Gate 10, and PLAN remain **OPEN** until the five checks above run for
real on Mac (or wherever the live stack and GitHub admin access are
available) and produce observed pass/fail evidence. This document is a
planning aid only — it must be **replaced** by real results recorded in
`docs/mac-certification-evidence.md` once run; its EXPECTED rows must never be
copied into that file as if they were observed.
