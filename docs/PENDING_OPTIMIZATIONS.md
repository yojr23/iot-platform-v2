# Pending Optimizations Backlog

This is a prioritized backlog of work that must be measured and validated before any performance or maintainability benefit is claimed. The observations below describe current responsibilities and intended boundaries; they are not measured gains.

## Acceptance gates for every item

Each item is complete only when all four gates are met:

1. **Baseline evidence:** capture representative latency, throughput, query counts, error rates, resource usage, and relevant logs/traces before changing behavior. Record workload, environment, and sample window.
2. **Focused automated tests:** add or update tests for the affected contracts, including failure, authorization, idempotency, reconnect, and compatibility cases where applicable.
3. **Staged rollout and rollback:** release behind a reversible flag or isolated stage, define abort thresholds, and document a rollback path that preserves existing route/message/data contracts.
4. **Post-change comparison:** rerun the same workload and compare against the baseline, including regressions and operational signals; publish evidence before retaining the change.

## 1. Benchmark and simplify `AlertService` queries

**Verified observation / boundary:** alert evaluation touches relations, rules, and idempotency state. The service must continue to preserve alert semantics and duplicate suppression. No query-count or latency improvement is verified yet.

**Deferred work:** benchmark relation loading, rule lookup, and idempotency queries under realistic alert volumes; inspect query plans and indexes; then simplify only the measured hot paths. If caching is introduced, define ownership, TTL, key versioning, invalidation on rule/device changes, and safe behavior after invalidation failure. A cache must never allow stale rules to bypass safety or idempotency guarantees.

**Acceptance gates:**

- Baseline: capture query count/shape, p50/p95/p99 evaluation latency, cache-free correctness, duplicate rate, and database load.
- Tests: cover relation/rule selection, concurrent duplicate delivery, rule updates, cache hit/miss, invalidation, expiry, and invalidation failure.
- Rollout: stage query changes and any cache independently; keep a kill switch and fall back to uncached reads on uncertainty.
- Comparison: verify equivalent alert decisions and idempotency while comparing query volume, latency, database load, and error signals.

## 2. Retire Blade by vertical with API-parity tests

**Verified observation / boundary:** Blade retirement is a migration concern, not an invitation to change API behavior. The migration order is devices, alerts/rules, then catalog/configuration.

**Deferred work:** migrate one vertical at a time. Establish API-parity tests before removing each Blade surface, covering payload shape, validation, authorization, pagination/filtering, error responses, and side effects. Remove a vertical only after the replacement is stable and observable.

**Acceptance gates:**

- Baseline: inventory routes/views and record response contracts, browser/API timings, error rates, and usage for the vertical.
- Tests: run focused API-parity and authorization tests against old and replacement flows, including empty, invalid, and failure cases.
- Rollout: migrate devices first, then alerts/rules, then catalog/configuration, with route-level fallback or rollback at each step.
- Comparison: compare contract conformance, completion/error rates, latency, and support/operational signals before deleting the old surface.

## 3. Split `SensorApiController` by responsibility

**Verified observation / boundary:** the controller currently spans multiple responsibilities. The intended seams are ingestion, CRUD, history, export, and graph-zone operations. Route contracts must not change.

**Deferred work:** extract responsibility-focused controllers or handlers while preserving route names, HTTP methods, parameters, response envelopes, authorization, validation, status codes, and serialization. Keep shared domain behavior in its existing service/model boundaries rather than duplicating it during extraction.

**Acceptance gates:**

- Baseline: inventory every route and measure representative latency, query count, response size, validation/auth failures, and export duration.
- Tests: add route-contract, authorization, validation, serialization, history, export, and graph-zone regression tests for each seam.
- Rollout: move one responsibility at a time behind reversible routing/configuration; retain the original handler for rollback.
- Comparison: replay the route matrix and compare responses, errors, queries, latency, and resource use byte-for-byte or with documented intentional differences.

## 4. Decompose `SensorMonitorBoard.vue` by visual and interaction responsibilities

**Verified observation / boundary:** the board contains multiple visual/interaction concerns. Template size alone does not establish a performance problem or a performance benefit.

**Deferred work:** identify stable boundaries such as sensor summary/status, readings/history, controls/actions, alerts, and graph-zone presentation; extract components around ownership and interaction contracts. Measure rendering and interaction behavior before and after decomposition, including update frequency and subscription cleanup.

**Acceptance gates:**

- Baseline: capture representative mount/update interaction timings, render/update counts, memory behavior, and user-visible errors for realistic sensor streams.
- Tests: cover component contracts, emitted events, loading/error/empty states, permissions, and subscription teardown.
- Rollout: release extracted areas incrementally with a reversible feature flag or route-level fallback.
- Comparison: compare the same interaction traces for responsiveness, update counts, memory, and correctness; do not claim gains from reduced template size by itself.

## 5. Evaluate persistent MQTT client lifecycle in `script_datos.py`

**Verified observation / boundary:** `script_datos.py` and `ingestion_service` both handle IoT-related work, but that overlap does not justify merging them. Reconnect and queue behavior are explicit compatibility requirements.

**Deferred work:** evaluate a persistent MQTT client lifecycle (connection ownership, reconnect backoff, subscription restoration, graceful shutdown, and queue draining) against the current behavior. Preserve message ordering/acknowledgement, retry, backpressure, and recovery semantics. Keep the script separate from `ingestion_service` unless measured ownership and operational evidence justify a later design change.

**Acceptance gates:**

- Baseline: measure connect/reconnect times, message throughput, queue depth/age, drops, duplicates, ordering, and recovery during broker/network faults.
- Tests: use broker/network fault tests for reconnect, resubscription, queue overflow, shutdown, duplicate delivery, and recovery.
- Rollout: canary the lifecycle change with a bounded queue and immediate rollback to the existing client path.
- Comparison: run equivalent fault and load scenarios and compare delivery guarantees, backlog recovery, resource use, and errors.

## 6. Retain central models and shared reading ownership

**Verified observation / boundary:** `Sensor`, `Device`, and `User` are central models. The outbox/consumers/recovery path is shared infrastructure, and `SensorReadingService` remains the shared owner for reading creation.

**Deferred work:** do not duplicate or relocate these responsibilities as a side effect of other refactors. Any future optimization must preserve model relationships, outbox/consumer/recovery guarantees, and the single reading-creation owner. Changes require explicit ownership and data-consistency evidence.

**Acceptance gates:**

- Baseline: document call paths and measure reading creation latency, transaction/query behavior, outbox lag, consumer lag, retries, and recovery outcomes.
- Tests: cover model relations, transaction boundaries, outbox publication, consumer retries/recovery, and `SensorReadingService` idempotency/ownership.
- Rollout: stage any change with dual-read or shadow verification where safe; retain a rollback path without creating competing writers.
- Comparison: reconcile persisted readings/events and compare consistency, lag, errors, and resource usage before accepting a change.

## 7. Add measured ingest/query observability before estimating gains

**Verified observation / boundary:** optimization estimates are not credible without ingest and query measurements. Errors and slow-operation signals are operationally important. Routine per-reading logging may be demoted only after operational validation.

**Deferred work:** instrument ingest and query paths with low-cardinality metrics, traces, query duration/count, queue/outbox lag, and slow-operation thresholds. Preserve error logs, actionable context, and slow-operation signals. Evaluate sampling or aggregation for routine per-reading logs only after confirming that incident diagnosis and audit needs remain covered.

**Acceptance gates:**

- Baseline: establish dashboards and a measurement window for throughput, p50/p95/p99 latency, query count/time, queue lag, error rate, and slow-operation count.
- Tests: verify instrumentation fields, correlation IDs, error/slow-path emission, sampling behavior, and alert thresholds without leaking sensitive data.
- Rollout: deploy telemetry incrementally with bounded cardinality and a rollback switch for logging/ sampling changes.
- Comparison: compare signal quality, overhead, incident diagnosability, and ingest/query behavior; estimate optimization gains only from this evidence.

## Non-negotiable preservation checklist

- Preserve route contracts while splitting controllers or retiring UI surfaces.
- Preserve reconnect, queue, ordering, acknowledgement, and recovery behavior in MQTT work.
- Preserve `Sensor`, `Device`, `User`, outbox/consumers/recovery, and `SensorReadingService` ownership boundaries.
- Preserve errors and slow-operation signals; demote routine per-reading logs only after validation.
- Treat every claimed gain as measured evidence, not an inference from smaller files, templates, or fewer abstractions.
