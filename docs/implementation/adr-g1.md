# Stage G1 — Architecture Decision Records

Implementation-start SHA `11be239` (`refraccion`). Decisions below are backed by a real Redis 7
Streams spike (`INTEGRATION_VERIFIED`) run this session, not prose alone. They bind Stages 2–10.

## Spike evidence (ADR-1/ADR-3)

Ran against Redis 7 (Docker `redis:7-alpine`, port 6399) via `predis` `executeRaw` (Redis-7-compatible
commands only: `XGROUP CREATE MKSTREAM`, `XADD`, `XREADGROUP … >`, `XPENDING`, `XAUTOCLAIM`, `XACK`).
All 10 assertions passed:

1. `XADD` with versioned envelope (`event_id`, `event_type`, `event_version=1`, `source`,
   `source_event_id`, `occurred_at`, `payload`).
2. Duplicate producer retry (same `source_event_id`) produces physically distinct stream entries →
   dedup is a **consumer** responsibility, proven next.
3–4. `XREADGROUP` delivers both; an idempotency ledger keyed by `source_event_id` applies the first,
   skips the duplicate. **Consumer crashes before `XACK`.**
5. `XPENDING` shows 2 unacked after the crash (no silent loss).
6–8. `XAUTOCLAIM` (idle=0) lets `worker-B` reclaim both pending messages — lease recovery **without a
   periodic PEL sweep**. Redelivery both dedup-skip; exactly **one** logical reading applied end-to-end
   (crash-after-apply-before-ack is safe).
9. `XPENDING` empty after `XACK`.
10. Poison message (invalid JSON) quarantined to `iot.dead-letter-events` **before** acking the original.

This proves at-least-once delivery + idempotent consumers + lease-based pending recovery + DLQ, which is
the core reliability contract of Stages 3–4.

## ADR-1 — Durable publication

**Decision (superseded scope, 8 September 2026):** the final architecture interprets **NO POLLING**
strictly: it includes periodic backend discovery of pending outbox rows. The target durable publication
path is therefore transactional outbox + a binlog/CDC relay with durable offsets. Reuse
`RawSensorEventPublisher` only as an optional low-level `XADD` transport while the transitional relay
exists.

**Transitional exception:** the current Laravel relay's `--interval=2` committed-outbox discovery loop
remains operational only until CDC is deployed and verified. It is not evidence of the final
zero-polling architecture and must be retired before the final architecture gate.

**Rejected:** `DB::afterCommit()`-only dispatch — a process can die after commit before the job is
queued, permanently stranding the row. `afterCommit()` is kept only as a low-latency **wake-up hint**.

**Required final anti-stranding mechanism:** CDC reads the committed binlog and persists connector offsets;
restart/recovery is driven by those offsets rather than a `SELECT pending` loop. A post-commit wake-up is
only a latency hint and never the delivery guarantee.

**Idempotency at schema level (Stage 2):** unique `(source, source_event_id)` on `raw_sensor_events`;
unique `(sensor_reading_id, alert_rule_id)` on `alerts` (closes the `AlertService:60` check-then-create
race); consumer ledger `(consumer_name, event_id)`.

## ADR-2 — Client recovery

**Decision:** lean V1 — a **lifecycle-triggered one-shot authenticated snapshot + resubscribe**.
Reconnect / `visibilitychange` / mobile-resume fire a single bounded authenticated request that returns
a consistent authorized snapshot; then resubscribe and merge buffered live events, deduping by event id.
No periodic GET. Defer the full cursor/replay/watermark protocol until reconnect frequency + payload size
are measured (audit §15a). Both are zero-polling-compatible; the lean one ships first.

## ADR-3 — DLQ / quarantine

**Decision:** dedicated **`iot.dead-letter-events`** stream for stream-consumer terminal failures
(spike-proven), storing `orig_id`, `reason`, `source_event_id`, `attempts`. Laravel `failed_jobs` is
acceptable for queue-relay job failures, with `queue:retry` as replay tooling. Initial policy: batch 100,
5 attempts, bounded backoff, claim threshold above max transaction duration (audit §18) — tunable.

## ADR-4 — Internal realtime fan-out

**Decision:** **direct** `browser-delivery-v1 consumer → Pusher-compatible broadcaster`. One broadcaster
in this deployment; no fan-out requirement. **No Redis Pub/Sub** until horizontal WebSocket scaling is
real (audit §11). Streams remain the durable backbone regardless.

## Deployment / crash matrix / rollback

- **Committed outbox row never stranded:** discovery loop + lease recovery (ADR-1).
- **Redis outage:** relay retries with queue backoff; outbox rows stay `pending`; no 201-after-failed-XADD
  (fix `IngestionController` to reflect receipt vs published status).
- **Consumer crash before/after ACK:** redelivery + idempotency ledger (spike-proven).
- **Poison event:** DLQ before ack (spike-proven).
- **Operational owner:** Laravel queue worker + a `raw:consume` artisan command (Stage 3).
- **Rollback:** release without deleting outbox/ledger/DLQ; never re-enable the polling client as
  steady state (audit §22).

**Environment note:** spike is `INTEGRATION_VERIFIED` on Redis 7 via Docker. No `phpredis` extension and
no local `redis-server` binary here → app runs `predis` client (added to `back`). Full app-level
integration tests that need Redis will run against the Docker container; where a test needs Redis and it
is unavailable in CI, label `ENVIRONMENT_CONSTRAINT`.
