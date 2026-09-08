# Gate S5-R Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Close the P0/P1 regressions identified after b412921 and produce the evidence required to approve Gate S5-R before the Stage 6 sensor-dashboard cutover.

**Architecture:** SensorReadingService becomes the sole application owner for a sensor reading: it persists the row, records sensor.reading.created, and schedules a post-commit Redis projection. DomainEventBroadcastConsumer transforms that durable fact into the existing NewSensorReading browser event. The raw stream normalizer, direct API, Compose workers, ingestion producer, and alert-recovery client are aligned around those boundaries.

**Tech Stack:** Laravel 12 / PHP 8.2, MySQL and Eloquent transactions, Redis Streams and lists, Vue 3 + Pinia + Laravel Echo, Python + Pydantic, Docker Compose.

**Spec:** audit.md sections 2–11 and 15–16; PLAN.md Stage 2, Stage 4, and Stage 5; docs/implementation/adr-g1.md.

## Global Constraints

- Do not begin Stage 6 UI migration, remove polling, or change the public NewSensorReading envelope in this plan.
- Commit a business mutation before XACK; retain the current DLQ and lease-reclaim behavior.
- Each successful logical reading records exactly one sensor.reading.created outbox fact.
- Redis is a best-effort projection. The database remains authoritative, but a committed reading is immediately visible from Redis where Redis is available.
- Recovery snapshots are lifecycle-triggered one-shot actions, never periodic polling. An older snapshot can never overwrite a newer state.
- source_event_id is created once per producer event and retained on retries; BackendClient must not create a replacement identity.
- PHP unit/feature tests use the isolated SQLite configuration in CLAUDE.md. Redis integration results are PASS only with an explicit reachable TEST_REDIS_HOST/TEST_REDIS_PORT; otherwise report SKIPPED as an environment constraint.

---

## File map

| File | Responsibility |
| --- | --- |
| back/app/Services/Ingestion/SensorReadingService.php | Transactional reading creation, domain outbox record, post-commit projection callback. |
| back/app/Services/Ingestion/SensorReadingProjectionService.php | Redis append, latest lookup, warm-up, and response formatting. |
| back/app/Http/Controllers/Api/SensorApiController.php | Auth/validation and service delegation; no Redis mechanics. |
| back/app/Services/Ingestion/RawReadingNormalizer.php | Sensor mapping and delegation to the sole reading owner. |
| back/app/Services/Ingestion/DomainEventBroadcastConsumer.php | Durable domain fact to Laravel browser event mapping. |
| docker-compose.yml | Domain outbox relay and browser-delivery workers in profile workers. |
| ingestion_service/app/schemas.py and normalizer.py | Stable official producer identity. |
| front/src/realtime/echo.js, useAlertsRealtime.js, stores/alerts.js | Mutation-safe listener notification and serial snapshot recovery. |

### Task 1: Restore the committed latest-readings projection through one service

**Files:**

- Create: back/app/Services/Ingestion/SensorReadingProjectionService.php
- Modify: back/app/Services/Ingestion/SensorReadingService.php
- Modify: back/app/Http/Controllers/Api/SensorApiController.php
- Create: back/tests/Feature/SensorReadingServiceTest.php
- Modify: back/tests/Feature/SensorApiControllerTest.php

**Interfaces:**

- Consumes: Sensor, SensorReading, DomainEventRecorder, and the existing key sensor:latest_readings:{sensorId}.
- Produces: SensorReadingService::createReading(Sensor $sensor, float $value, ?string $readingTime): SensorReading and projection methods append, latest, warm, and format.

- [ ] **Step 1: Write the failing direct-path tests.**

~~~php
$reading = app(SensorReadingService::class)
    ->createReading($sensor, 21.5, '2026-09-08T12:00:00Z');

$this->assertDatabaseHas('domain_event_outbox', [
    'event_type' => 'sensor.reading.created',
    'aggregate_id' => (string) $reading->id,
]);
$this->assertSame(1, DomainEventOutbox::where('aggregate_id', $reading->id)->count());
$this->getJson("/api/sensors/{$sensor->id}/latest-readings?limit=1")
    ->assertOk()->assertJsonPath('0.id', $reading->id);
~~~

Add cases for a Redis miss that warms from DB and unusable cached JSON that falls back safely.

- [ ] **Step 2: Run the focused tests to demonstrate the missing projection boundary.**

Run: cd back; php artisan test --filter='(SensorReadingServiceTest|SensorApiControllerTest)'

Expected: the new immediate-visibility test fails before a post-commit projection exists.

- [ ] **Step 3: Extract Redis mechanics into the projection service.**

~~~php
final class SensorReadingProjectionService
{
    public function append(SensorReading $reading): void
    {
        $payload = json_encode($this->format($reading), JSON_UNESCAPED_UNICODE);
        Redis::pipeline(fn ($pipe) => [$pipe->lpush($this->key($reading->sensor_id), $payload), $pipe->ltrim($this->key($reading->sensor_id), 0, 119)]);
    }
    public function latest(int $sensorId, int $limit): ?Collection
    {
        $items = collect(Redis::lrange($this->key($sensorId), 0, $limit - 1))->map(fn ($json) => json_decode($json, true));
        return $items->every('is_array') ? $items : null;
    }
    public function warm(int $sensorId, Collection $readings): void
    {
        Redis::pipeline(function ($pipe) use ($sensorId, $readings) { $pipe->del($this->key($sensorId)); foreach ($readings->reverse() as $reading) { $pipe->lpush($this->key($sensorId), json_encode($this->format($reading), JSON_UNESCAPED_UNICODE)); } $pipe->ltrim($this->key($sensorId), 0, 119); });
    }
    public function format(SensorReading|array $reading): array
    {
        if ($reading instanceof SensorReading) {
            return ['id' => $reading->id, 'value' => (float) $reading->value, 'reading_time' => $reading->reading_time?->toIso8601String(), 'created_at' => $reading->created_at?->toIso8601String()];
        }
        return ['id' => $reading['id'], 'value' => (float) $reading['value'], 'reading_time' => $reading['reading_time'], 'created_at' => $reading['created_at']];
    }
}
~~~

Each Redis-facing method catches Throwable, logs the sensor id and exception, and returns null or void; a cache failure never rolls back the committed reading or prevents the database fallback.

Move cacheLatestReadingInRedis, warmLatestReadingsInRedis, getLatestReadingsFromRedis, getSensorReadingsRedisKey, and formatReadingForResponse out of SensorApiController. Preserve its limit clamp, logging, and DB query but delegate all projection work.

- [ ] **Step 4: Schedule append after the outermost transaction commits.**

~~~php
return DB::transaction(function () use ($sensor, $value, $readingTime) {
    $reading = $sensor->readings()->create(['value' => $value, 'reading_time' => $readingTime ?? now()]);
    $this->recorder->record('sensor.reading.created', 'sensor_reading', $reading->id, [
        'reading_id' => $reading->id, 'sensor_id' => $sensor->id, 'value' => $value,
        'reading_time' => $reading->reading_time?->toIso8601String(),
    ]);
    DB::afterCommit(fn () => $this->projection->append($reading));
    return $reading->load('sensor.sensorType', 'sensor.device.lab');
});
~~~

The outbox row must be written before scheduling the projection callback. Nested calls from RawStreamConsumer therefore project only after its outer transaction commits.

- [ ] **Step 5: Re-run focused tests.**

Run: cd back; php artisan test --filter='(SensorReadingServiceTest|SensorApiControllerTest)'

Expected: PASS, including cache fallback/warm-up and direct immediate visibility.

- [ ] **Step 6: Commit the independently reviewable boundary.**

~~~bash
git add back/app/Services/Ingestion/SensorReadingProjectionService.php back/app/Services/Ingestion/SensorReadingService.php back/app/Http/Controllers/Api/SensorApiController.php back/tests/Feature/SensorReadingServiceTest.php back/tests/Feature/SensorApiControllerTest.php
git commit -m "fix: project committed sensor readings to redis"
~~~

### Task 2: Deliver sensor.reading.created to browsers exactly once

**Files:**

- Modify: back/app/Services/Ingestion/DomainEventBroadcastConsumer.php
- Modify: back/tests/Feature/DomainEventBroadcastConsumerTest.php

**Interfaces:**

- Consumes: a DomainEventOutbox payload with reading_id.
- Produces: NewSensorReading dispatch, delivered_at persistence, and XACK.

- [ ] **Step 1: Add failing consumer tests for sensor delivery and redelivery.**

~~~php
Event::fake([NewSensorReading::class]);
$outbox = $this->sensorReadingOutbox();
$this->xadd($outbox->id, 'sensor.reading.created');
$this->consumer()->runOnce('worker-A', 10, 100);

Event::assertDispatchedTimes(NewSensorReading::class, 1);
$this->assertNotNull($outbox->fresh()->delivered_at);
~~~

Add a second XADD for that same row and assert dispatch remains once.

- [ ] **Step 2: Run the consumer suite against Redis.**

Run: cd back; $env:TEST_REDIS_PORT='6399'; php artisan test --filter=DomainEventBroadcastConsumerTest

Expected: the new case fails with unknown domain event_type before the registry entry exists, or the entire suite is documented as environment-skipped when Redis cannot be reached.

- [ ] **Step 3: Implement the registry entry and aggregate reconstruction.**

~~~php
'sensor.reading.created' => $this->broadcastSensorReadingCreated($outbox),

private function broadcastSensorReadingCreated(DomainEventOutbox $outbox): void
{
    $reading = SensorReading::query()
        ->with(['sensor.sensorType', 'sensor.device.lab'])
        ->find(data_get($outbox->payload, 'reading_id'));

    if ($reading) {
        event(new NewSensorReading($reading));
    }
}
~~~

Use the existing alert/device pattern for a deleted target: log a terminal outcome rather than retry forever. Do not restore a direct controller event.

- [ ] **Step 4: Run the complete consumer regression matrix.**

Run: cd back; $env:TEST_REDIS_PORT='6399'; php artisan test --filter=DomainEventBroadcastConsumerTest

Expected: PASS with alert, device, sensor, duplicate, pending-reclaim, unknown-row, and already-delivered coverage.

- [ ] **Step 5: Commit.**

~~~bash
git add back/app/Services/Ingestion/DomainEventBroadcastConsumer.php back/tests/Feature/DomainEventBroadcastConsumerTest.php
git commit -m "fix: broadcast durable sensor reading events"
~~~

### Task 3: Make RawReadingNormalizer use the same creation owner

**Files:**

- Modify: back/app/Services/Ingestion/RawReadingNormalizer.php
- Modify: back/tests/Feature/RawStreamConsumerTest.php
- Modify: back/tests/Feature/SensorReadingServiceTest.php

**Interfaces:**

- Consumes: RawSensorEvent and constructor-injected SensorReadingService.
- Produces: for every accepted mapping, one reading and one durable sensor fact in RawStreamConsumer's existing transaction.

- [ ] **Step 1: Extend the raw success and duplicate tests.**

~~~php
$stats = $this->consumer()->runOnce('worker-A', 10, 100);
$this->assertSame(1, $stats['acked']);
$this->assertSame(1, $sensor->readings()->count());
$this->assertDatabaseCount('domain_event_outbox', 1);
$this->assertDatabaseHas('domain_event_outbox', ['event_type' => 'sensor.reading.created']);
~~~

- [ ] **Step 2: Run the raw test and verify the missing fact fails.**

Run: cd back; $env:TEST_REDIS_PORT='6399'; php artisan test --filter=RawStreamConsumerTest

Expected: new outbox assertions fail before delegation.

- [ ] **Step 3: Replace the direct Eloquent mutation with delegation.**

~~~php
public function __construct(private SensorReadingService $readings) {}

$this->readings->createReading($sensor, (float) $value, (string) $readingTime);
~~~

Remove sensor->readings()->create from RawReadingNormalizer. Update direct new RawReadingNormalizer test construction to inject app(SensorReadingService::class). Leave RawStreamConsumer locking, status transition, and outer DB transaction unchanged.

- [ ] **Step 4: Run both mutation-path suites.**

Run: cd back; $env:TEST_REDIS_PORT='6399'; php artisan test --filter='(RawStreamConsumerTest|SensorReadingServiceTest|SensorApiControllerTest)'

Expected: PASS. A duplicate raw stream entry still yields only one reading and one fact.

- [ ] **Step 5: Commit.**

~~~bash
git add back/app/Services/Ingestion/RawReadingNormalizer.php back/tests/Feature/RawStreamConsumerTest.php back/tests/Feature/SensorReadingServiceTest.php
git commit -m "fix: unify raw and api reading creation"
~~~

### Task 4: Deploy the domain relay and browser-delivery worker

**Files:**

- Modify: docker-compose.yml
- Modify: back/.env.example
- Modify: README.md

**Interfaces:**

- Consumes: DOMAIN_EVENTS_STREAM=iot.domain-events and DOMAIN_EVENTS_CONSUMER_GROUP=browser-delivery-v1.
- Produces: domain-outbox-relay running domain:relay-outbox --interval=2 and domain-event-consumer running domain:consume --block=5000 under the workers profile.

- [ ] **Step 1: Add the failing rendered-Compose assertion.**

~~~powershell
$compose = docker compose --profile workers config
if ($compose -notmatch 'domain-outbox-relay' -or $compose -notmatch 'domain-event-consumer') {
  throw 'domain workers missing'
}
~~~

- [ ] **Step 2: Confirm the services are absent before the edit.**

Run: docker compose --profile workers config

Expected: no domain-outbox-relay or domain-event-consumer service.

- [ ] **Step 3: Add workers by reusing worker_env and the existing worker dependency structure.**

~~~yaml
domain-outbox-relay:
  environment: *worker_env
  command: php artisan domain:relay-outbox --interval=2
domain-event-consumer:
  environment: *worker_env
  command: php artisan domain:consume --block=5000
~~~

Add domain stream/group variables to worker_env and back/.env.example. Keep restart: unless-stopped, profile workers, volumes, and healthy db/redis/back dependencies identical to outbox-relay and raw-consumer.

- [ ] **Step 4: Validate rendered config and worker boot.**

Run: docker compose --profile workers config

Expected: both services render with resolved stream/group values.

Run: docker compose --profile workers up -d domain-outbox-relay domain-event-consumer

Expected: both containers remain running; logs show relay sweeps and domain:consume started.

- [ ] **Step 5: Document and commit.**

~~~bash
git add docker-compose.yml back/.env.example README.md
git commit -m "chore: run domain event workers in compose"
~~~

### Task 5: Give the official producer a stable source_event_id

**Files:**

- Modify: ingestion_service/app/schemas.py
- Modify: ingestion_service/app/normalizer.py
- Modify: ingestion_service/app/mqtt_client.py
- Modify: ingestion_service/tests/test_normalizer.py
- Modify: ingestion_service/tests/test_backend_client.py

**Interfaces:**

- Consumes: a transport supplied identity when available, else a generated identity created once in the MQTT message boundary.
- Produces: RawIngestionEvent.source_event_id and unchanged retries through BackendClient.

- [ ] **Step 1: Add failing identity tests.**

~~~python
event = build_raw_event(
    payload,
    topic="iot/lab/readings",
    source_event_id="mqtt:lab-01:packet-42",
)
assert event["source_event_id"] == "mqtt:lab-01:packet-42"
assert build_raw_event(payload, topic="iot/lab/readings")["source_event_id"]
~~~

Test that retrying the same raw-event object sends the same field.

- [ ] **Step 2: Run the producer tests before implementation.**

Run: cd ingestion_service; pytest tests/test_normalizer.py tests/test_backend_client.py -q

Expected: FAIL because RawIngestionEvent currently has no source_event_id.

- [ ] **Step 3: Extend the Pydantic contract and producer boundary.**

~~~python
class RawIngestionEvent(BaseModel):
    source_event_id: str = Field(min_length=1, max_length=255)

def build_raw_event(
    payload: dict[str, Any], *, topic: str | None, received_at: str | None = None,
    source: str = "ingestion_service", source_event_id: str | None = None,
) -> dict[str, Any]:
    event_id = source_event_id or str(uuid4())
~~~

Use broker packet identity if exposed; otherwise generate once in on_message and pass it through retry state. Never generate it inside BackendClient.send_raw_event.

- [ ] **Step 4: Run producer plus backend idempotency tests.**

Run: cd ingestion_service; pytest tests/test_normalizer.py tests/test_backend_client.py -q

Run: cd ../back; php artisan test --filter='(IngestionApiTest|RawSensorEventIdempotencyTest)'

Expected: PASS. Same source/id remains idempotent while different sources may share an id.

- [ ] **Step 5: Commit.**

~~~bash
git add ingestion_service/app/schemas.py ingestion_service/app/normalizer.py ingestion_service/app/mqtt_client.py ingestion_service/tests/test_normalizer.py ingestion_service/tests/test_backend_client.py
git commit -m "fix: identify raw ingestion events at the producer"
~~~

### Task 6: Make alert recovery deterministic and test real echo.js

**Files:**

- Modify: front/package.json
- Modify: front/src/realtime/echo.js
- Modify: front/src/realtime/useAlertsRealtime.js
- Modify: front/src/stores/alerts.js
- Modify: front/scripts/verify-phase5.mjs
- Create: front/src/realtime/echo.test.js
- Create: front/src/realtime/useAlertsRealtime.test.js

**Interfaces:**

- Consumes: only a mocked laravel-echo low-level Pusher transport, fetchActiveAlerts options throwOnError and apply, and lifecycle reasons reconnect, visibility, auth.
- Produces: snapshot iteration notification, a single active state-mutating recovery, stale status on failure/overflow, and live status only after the current snapshot applies.

- [ ] **Step 1: Add a behavioral unit runner and failing production-module tests.**

~~~json
"test:realtime": "vitest run src/realtime"
~~~

Mock laravel-echo and connector.pusher.connection, then import the actual echo.js. Assert that a callback that unsubscribes and resubscribes during auth:changed is invoked once for that notification. Add alert recovery tests for: already-connected late subscribe; rejected snapshot; reconnect plus visibility overlap; overflow followed by old snapshot resolution; and a live alert buffered during snapshot.

- [ ] **Step 2: Run the tests to demonstrate the races.**

Run: cd front; npm run test:realtime

Expected: FAIL before the changes. The tests must never replace ./echo with a virtual lifecycle implementation.

- [ ] **Step 3: Snapshot listener iteration and let recovery observe fetch errors.**

~~~js
function notify(listeners, ...args) {
  [...listeners].forEach((callback) => callback(...args));
}

async fetchActiveAlerts({ silent = false, notifyNew = false, throwOnError = false, apply = true } = {}) {
  try {
    const response = await getActiveAlerts();
    const alerts = response.data?.alerts ?? [];
    if (!apply) return alerts;
    return this.applyActiveSnapshot(alerts, { notifyNew });
  }
  catch (error) {
    this.error = getApiErrorMessage(error);
    if (throwOnError) throw error;
    return [];
  }
}
~~~

Set connecting status before registering the immediate connection listener. Call recovery with throwOnError: true and apply: false so only the current generation applies the snapshot.

- [ ] **Step 4: Serialize and guard generations.**

~~~js
let activeRecovery = null;
let recoveryRequested = false;
let recoveryGeneration = 0;

function requestRecovery(store) {
  if (activeRecovery) {
    recoveryRequested = true;
    return activeRecovery;
  }
  const generation = ++recoveryGeneration;
  activeRecovery = runSnapshot(store, generation).finally(() => {
    activeRecovery = null;
    if (recoveryRequested) {
      recoveryRequested = false;
      requestRecovery(store);
    }
  });
  return activeRecovery;
}
~~~

Before applyActiveSnapshot and replay, runSnapshot checks that its generation remains current. Overflow invalidates the current generation, records the triggering event or marks its loss explicitly, stays stale, and requests exactly one replacement when the active request settles.

- [ ] **Step 5: Run client checks and build.**

Run: cd front; npm run test:realtime; npm run test:phase5; npm run build

Expected: PASS. Keep verify-phase5 as a structural guard only; update assertions only where the old expectation contradicts tested behavior.

- [ ] **Step 6: Commit.**

~~~bash
git add front/package.json front/package-lock.json front/src/realtime/echo.js front/src/realtime/useAlertsRealtime.js front/src/stores/alerts.js front/src/realtime/echo.test.js front/src/realtime/useAlertsRealtime.test.js front/scripts/verify-phase5.mjs
git commit -m "fix: serialize realtime alert recovery"
~~~

### Task 7: Run the Gate S5-R evidence pack and update the tracker

**Files:**

- Modify: PLAN.md
- Modify: audit.md

**Interfaces:**

- Consumes: test output, Compose service state, and direct/raw end-to-end evidence.
- Produces: a dated ledger stating status, commits, exact verification, and remaining blockers without declaring Gate 6 complete.

- [ ] **Step 1: Run the isolated backend suite.**

Run: cd back; $env:APP_ENV='testing'; $env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'; $env:CACHE_STORE='array'; $env:SESSION_DRIVER='array'; $env:QUEUE_CONNECTION='sync'; php artisan test

Expected: PASS. Record failures; do not weaken assertions to obtain a pass.

- [ ] **Step 2: Run the Redis stream suites using Compose Redis.**

Run: docker compose up -d redis

Run: cd back; $env:TEST_REDIS_HOST='127.0.0.1'; $env:TEST_REDIS_PORT='6379'; php artisan test --filter='(RawStreamConsumerTest|DomainEventBroadcastConsumerTest)'

Expected: PASS with duplicate/reclaim cases, or documented environment constraint if Redis cannot be made reachable.

- [ ] **Step 3: Verify both end-to-end delivery paths with mandatory workers.**

~~~text
direct POST -> reading + one outbox row -> domain relay -> domain consumer -> NewSensorReading
raw receipt -> RawStreamConsumer -> same service + one outbox row -> same relay/consumer -> NewSensorReading
~~~

Confirm delivered_at is set only after dispatch and replaying the same stream message does not emit another event.

- [ ] **Step 4: Run frontend proof and label its limits.**

Run: cd front; npm run test:realtime; npm run build; npm run audit:events

Expected: PASS for unit/injected-handler coverage. A real Pusher-compatible browser WebSocket test is separately labeled until it exists; injected callbacks are not claimed as transport evidence.

- [ ] **Step 5: Add the progress ledger after proof and commit it.**

Add a compact PLAN.md table with Stage, status, commits, verification, and remaining blockers. Mark immediate P0/P1 audit items resolved only when their named proof passed. Keep Stage 6–10 as deferred/not started.

~~~bash
git add PLAN.md audit.md
git commit -m "docs: record gate s5r verification"
~~~

## Gate S5-R exit checklist

- [ ] Direct API and raw stream create readings only through SensorReadingService and each successful logical reading has one durable fact.
- [ ] sensor.reading.created is relayed, consumed, emitted as NewSensorReading, and redelivery is suppressed.
- [ ] Latest-readings sees the committed reading through Redis when available and falls back/warm-ups safely.
- [ ] domain-outbox-relay and domain-event-consumer run in the normal workers Compose profile.
- [ ] The official producer supplies source_event_id before HTTP submission.
- [ ] Echo listener iteration is mutation-safe; alert recovery propagates failure, serializes snapshots, and rejects stale generations.
- [ ] Backend suite, Redis-stream suite, realtime unit suite, frontend build, and Compose smoke test have recorded results.

## Explicitly deferred

This closure excludes the shared sensor-readings Pinia projection, SensorMonitorBoard history/live merge and polling removal (Stage 6); alert polling retirement/no-poll network proof (Stage 7); asynchronous alert-triggered email/webhook delivery (Stage 8); repository artifact cleanup; and the Stage 9/10 observability programme. Start those only after every Gate S5-R exit item has evidence.
