# Public Graph Visibility — Agentic Execution Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Make the approved realtime graph the only intentionally public telemetry surface, with one explicit per-sensor, fail-closed backend decision across REST and browser delivery.

**Architecture:** SensorReadingService always writes the reading and sensor.reading.created outbox fact. DomainEventBroadcastConsumer is the only browser-delivery decision point and asks PublicGraphVisibility before it dispatches NewSensorReading. Public REST asks the same service before projecting a minimal bootstrap or a bounded series. Vue uses the existing Echo/channel registry and one scoped Pinia projection; it is never the authorization boundary.

**Tech Stack:** Laravel 12 / PHP 8.2 / Eloquent / Redis Streams / Laravel Echo-Pusher, Vue 3 / Pinia / Chart.js / Vitest / Playwright.

**Spec:** front_rebuild_plan/FRONT_REBUILD_PLAN_ADJUSTMENTS_v1.1.md; front_rebuild_plan/SINOA_Agentic_Dashboard_Implementation_Plan_v2.1_PUBLIC_REALTIME.md; PLAN.md Stage 6.

## Global Constraints

- Persist exactly sensors.public_monitoring_enabled BOOLEAN NOT NULL DEFAULT FALSE; do not create is_public, a Device/Lab flag, a global enable switch, or a blanket true backfill.
- App\Services\Monitoring\PublicGraphVisibility is the only public-visibility owner. Its exact rule is $sensor->public_monitoring_enabled === true; status and is_active never stand in for it.
- Preserve readings, outbox facts, Redis Stream entries, and the internal SensorReadingProjectionService for public and restricted sensors. Do not create a public projection, cache, or stream.
- The graph bootstrap, graph series, and public sensor broadcast all use the same policy. A restricted or guessed public sensor ID returns 404 and produces no public event.
- The current schema truthfully supplies only device id/name, sensor id/name, and sensorType.unit. Do not fabricate precision, thresholds, cadence, quality, Lab, or device-status metadata.
- Public REST is hydration/lifecycle recovery only. There is no frontend polling or fallback polling.
- /api/alerts/active is an explicit Stage 7 route migration. Do not claim the final graph-only public surface until that route and the alert channel are authorized.

## v1.3 pre-Stage-6 corrections — authoritative

Evidence: `docs/implementation/pre-stage6-evidence.md`, `docs/implementation/reading-time-semantics.md`. Overrides conflicting text below.

- The **Contract Freeze** table's `raw|1m`, 2,000-sample, 50,000-row bounds are **illustrative, not frozen facts** — Stage 6 selects them from a measured EXPLAIN on the new `sensor_readings(sensor_id, reading_time, id)` index (migration added). Pre-Stage-6 freezes only timestamp grammar (post timezone probe), half-open `[from,to)`, DB-as-truth, bounded query, no silent truncation.
- **`ValidationException::withMessages(...)`** is the 422 mechanism (already reflected). **`/api/iot/sensors`** is credentialed ingestion (401 on missing/wrong key), **`/api/health`** an anonymous liveness exception — neither is a guest product API.
- **Canonical entry = Vue SPA at `FRONT_URL/dashboard`** (Blade dashboard retired). Transitional public APIs are cut atomically with the Stage 6 replacement, not before.
- **Ownership:** live projection key = `sensorId`; historical query key = `authorizationScope + sensorId + from + to + aggregation`; Pinia does not own subscription release.
- **Authenticated restricted-sensor realtime** uses the private `sensor.{id}` channel added in preflight; the consumer (not `NewSensorReading`) decides audience.
- **Time semantics = C (mixed/ambiguous) → Task 1 and Stage 6 remain BLOCKED** until a historical migration/normalization decision lands. Never append `Z` to offsetless timestamps.

## v1.2 blocking preflight — perform before Task 1

This plan is a design/implementation plan, not authority to commit or deploy. A worker records `git diff` and test evidence; it commits only when the operator explicitly authorizes it.

1. **Choose the only anonymous dashboard entry point.** The current Laravel `/dashboard` Blade controller renders broad metrics, alerts, and unrestricted inventory, while `front/src/views/DashboardView.vue` is a second dashboard. Before retiring any API, route anonymous `/dashboard` to the deployed Lab Blue SPA or reduce the Blade response to the graph-only contract. Retire, redirect, or authenticate the other surface in the same cutover. Include `back/routes/web.php`, `back/app/Http/Controllers/DashboardController.php`, and `back/resources/views/dashboard.blade.php` in the change map and prove the old public HTML cannot expose metrics/alerts/inventory.
2. **Inventory every anonymous route, not just the three Vue callers.** Retire/protect `/api/config/public`; it exposes alert policy and the obsolete polling interval. Review `/api/iot/sensors` as ingestion-only and give it an explicit credential/internal-network boundary. `/api/health` may remain only as a documented infrastructure liveness endpoint with no dashboard role; otherwise protect/relocate it. The end-state anonymous product-domain routes are exactly graph bootstrap and graph series, plus the approved graph-reading channel. `/api/alerts/active` is protected in Task 7.
3. **Prove UTC before promising UTC.** `APP_TIMEZONE` defaults to `America/Bogota`, `sensor_readings.reading_time` is a Laravel timestamp, and legacy ingestion accepts local `Y-m-d H:i:s`. Write a one-time DB/application probe with known instants, decide how historical values are interpreted/migrated, normalize new writes, and reject ambiguous input. Freeze the graph request as second-precision `YYYY-MM-DDTHH:mm:ssZ` and `[from,to)` only after that evidence exists.
4. **Make bounded range queries safe and complete.** Create/benchmark a migration for `sensor_readings(sensor_id, reading_time, id)`. The indexed database is the range source of truth. `SensorReadingProjectionService` is an internal 120-entry latest cache and cannot serve a response if it would omit an in-window row.
5. **Migrate legacy public-event consumers before contracting payload.** The public Blade dashboard listens to all sensor channels and Blade sensor pages use `data.unit`. Retire/redirect the public Blade dashboard, and modify any retained authenticated subscriber to take labels/units from already-authorized page data before Task 4 removes event metadata. No enriched public-event compatibility fallback is allowed.
6. **Freeze only honest V1 graph semantics.** The current schema supports device id/name, sensor id/name, unit, sample values/timestamps and min/max/mean/count. It does not support public thresholds, precision, quality, expected cadence, completeness, a stale cutoff, or device-connectivity classification. V1 shows last-observed/no-data/loading/error and browser transport state. Any richer semantic needs its own owner, public-safety decision, contract, migration/configuration source, and tests.

Visibility is evaluated at browser-delivery time using the current sensor flag. Consequently enablement exposes the sensor's permitted bounded historical series as well as later deliveries; disablement suppresses facts not yet broadcast. A temporal visibility cutoff is a different product policy and is out of scope.

## Contract Freeze

| Operation | Contract | Enforcement |
| --- | --- | --- |
| Bootstrap | GET /api/public/graph/bootstrap → { version: 1, default_sensor_id: integer\|null, devices: [{ id, name, sensors: [{ id, name, unit }] }] } | publicSensorsQuery()->with(['sensorType', 'device']); sort device then sensor deterministically, omit devices with zero public sensors, make `default_sensor_id` null for an empty catalog, and expose every non-graph field nowhere. |
| Series | GET /api/public/graph/sensors/{sensor}/series?from=YYYY-MM-DDTHH:mm:ssZ&to=YYYY-MM-DDTHH:mm:ssZ&aggregation=raw\|1m | Bind then call requirePublic. The exact UTC grammar and `[from,to)` semantics begin only after the timezone preflight. Existing restricted and guessed IDs all return 404. |
| Bounds | RFC3339 UTC timestamps; from < to; raw at most 1 hour/2,000 samples; 1-minute aggregation at most 24 hours/1,440 buckets and 50,000 source samples. | Unknown aggregation or an exceeded bound returns documented 422; never silently truncate. |
| Point semantics | { timestamp, value, min, max, sample_count, reading_id }; reading_id is an integer for raw and null for a one-minute bucket. Response includes original valid-sample min/max/mean/count and gap_encoding: absent-bucket. | Never synthesize zero/null samples for gaps. V1 has no threshold, quality, cadence, coverage, precision, stale, or device-status field. |
| Realtime | Existing public sensor.{id} and a reduced NewSensorReading representation containing reading identity, sensor ID, value, timestamp, and envelope. | Consumer dispatches only after policy approval; the event has no policy/database lookup or sensor/device/lab metadata. |

The range adapter maps 1m, 5m, and 1h to raw; it maps 6h and 24h to one-minute aggregation. The raw point guard protects high-frequency sensors instead of silently losing scientific source data.

## Repository Change Map

| Area | Files | Responsibility |
| --- | --- | --- |
| Explicit flag and query index | back/database/migrations/*_add_public_monitoring_enabled_to_sensors_table.php; back/database/migrations/*_add_sensor_readings_graph_range_index.php; back/app/Models/Sensor.php; back/app/Http/Resources/SensorResource.php; back/app/Http/Controllers/API/SensorApiController.php; back/database/factories/SensorFactory.php; front/src/views/SensorsView.vue | Persist/edit visibility through the existing admin sensor owner and make the range access path index-backed. |
| Policy + REST | back/app/Services/Monitoring/PublicGraphVisibility.php; back/app/Services/Monitoring/PublicGraphSeriesService.php; back/app/Http/Controllers/Api/PublicGraphController.php; back/routes/api.php; back/app/Http/Controllers/Api/ConfigController.php | One policy, minimal bootstrap, bounded DB-backed series, and full anonymous-route contraction. |
| Public-entry and browser delivery | back/routes/web.php; back/app/Http/Controllers/DashboardController.php; back/resources/views/dashboard.blade.php; back/resources/views/sensors/index.blade.php; back/app/Services/Ingestion/DomainEventBroadcastConsumer.php; back/app/Console/Commands/ConsumeDomainEvents.php; back/app/Events/NewSensorReading.php; back/tests/Unit/EventEnvelopeTest.php | Eliminate the broad anonymous Blade dashboard before public event contraction; retained authorized views derive labels/unit locally; consumer applies policy after durable publication and before minimal dispatch. |
| Backend proof | back/tests/Feature/PublicGraphControllerTest.php; back/tests/Feature/DomainEventBroadcastConsumerTest.php; back/tests/Feature/SensorApiControllerTest.php; back/tests/Feature/Phase2ApiEndpointsTest.php | Fail-closed REST, consumer, admin, and legacy-route regression tests. |
| Frontend cutover | front/src/api/publicGraph.js; front/src/stores/sensorReadings.js; front/src/realtime/useSensorRealtime.js; front/src/views/DashboardView.vue; front/src/components/dashboard/SensorMonitorBoard.vue | Scoped adapters/projection; remove broad guest requests and polling during Stage 6. |

## Tasks

### Task 0: Close the preflight evidence before changing a public contract

**Files:** `back/routes/web.php`, `back/routes/api.php`, `back/app/Http/Controllers/DashboardController.php`, `back/app/Http/Controllers/Api/ConfigController.php`, legacy Blade subscribers, a migration for the graph range index, and focused feature/route tests.

- [ ] Capture the current anonymous route table and a no-session `/dashboard` response. Record the broad Blade data it currently contains and select the deployed Lab Blue guest entry point.
- [ ] Probe a known UTC instant through the application and database connection, decide the legacy-timestamp conversion/normalization path, and add a regression test before allowing a UTC graph request contract.
- [ ] Add and benchmark the composite graph index against a representative bounded query. Record the query plan/evidence; do not rely on a full-table scan or the truncated Redis cache.
- [ ] Replace the broad public Blade dashboard and any old payload consumer, then prove that an authenticated legacy sensor page (if retained) still renders a unit without `NewSensorReading.unit`.
- [ ] Delete/protect the obsolete public configuration endpoint and classify/protect the IoT-sensor and health routes according to the explicit route inventory.

**Exit:** only the selected guest entry point is anonymously reachable for dashboard monitoring; its route/API trace is graph-only; time semantics and index evidence exist; no retained page relies on a public enriched reading event.

### Task 1: Persist and administratively manage explicit visibility

**Files:**

- Create: back/database/migrations/<timestamp>_add_public_monitoring_enabled_to_sensors_table.php
- Modify: back/app/Models/Sensor.php, back/app/Http/Resources/SensorResource.php, back/app/Http/Controllers/API/SensorApiController.php, back/database/factories/SensorFactory.php, front/src/views/SensorsView.vue
- Test: back/tests/Feature/SensorApiControllerTest.php

**Interfaces:** Sensor casts public_monitoring_enabled to boolean; the protected create/update payload accepts public_monitoring_enabled: boolean; no additional endpoint, role, or configuration owner exists.

- [ ] **Step 1: Write the failing persistence and admin test.**

~~~
public function test_new_sensor_is_not_public_by_default(): void
{
    $this->assertFalse(Sensor::factory()->create()->public_monitoring_enabled);
}

public function test_admin_can_explicitly_enable_public_graph_monitoring(): void
{
    $admin = User::factory()->create(['is_admin' => true]);
    $sensor = Sensor::factory()->create(['public_monitoring_enabled' => false]);

    $this->actingAs($admin)->putJson("/api/sensors/{$sensor->id}", [
        'name' => $sensor->name,
        'device_id' => $sensor->device_id,
        'sensor_type_id' => $sensor->sensor_type_id,
        'public_monitoring_enabled' => true,
    ])->assertOk()->assertJsonPath('public_monitoring_enabled', true);
}
~~~

- [ ] **Step 2: Run it before implementation.**

Run: cd back; php artisan test --filter=SensorApiControllerTest

Expected: failure because the database attribute, cast, request validation, and resource field do not exist.

- [ ] **Step 3: Add the migration, model fields, resource field, payload rule, factory default, and existing-form switch.**

~~~
$table->boolean('public_monitoring_enabled')->default(false);

// Sensor::$casts
'public_monitoring_enabled' => 'boolean',

// SensorApiController::validatedSensorPayload()
'public_monitoring_enabled' => ['sometimes', 'boolean'],
~~~

The Vue form adds sensorForm.public_monitoring_enabled in its default/edit/submit paths and labels a distinct switch Allow public graph monitoring; it is not coupled to the existing Activo switch.

- [ ] **Step 4: Run focused verification.**

Run: cd back; php artisan test --filter=SensorApiControllerTest; vendor/bin/pint --dirty

Expected: a field-omitted create stays false; only an authorized explicit update makes it true.

- [ ] **Step 5: Commit.**

~~~
git add back/database/migrations back/app/Models/Sensor.php back/app/Http/Resources/SensorResource.php back/app/Http/Controllers/API/SensorApiController.php back/database/factories/SensorFactory.php front/src/views/SensorsView.vue back/tests/Feature/SensorApiControllerTest.php
git commit -m "feat: add explicit public graph sensor visibility"
~~~

### Task 2: Centralize the fail-closed policy

**Files:**

- Create: back/app/Services/Monitoring/PublicGraphVisibility.php
- Test: back/tests/Feature/PublicGraphControllerTest.php

**Interfaces:**

~~~
final class PublicGraphVisibility
{
    public function isPublic(Sensor $sensor): bool;
    public function publicSensorsQuery(): Builder;
    public function requirePublic(Sensor $sensor): Sensor;
}
~~~

- [ ] **Step 1: Write the status-independence test.**

~~~
public function test_visibility_is_not_derived_from_operational_status(): void
{
    $publicOffline = Sensor::factory()->create([
        'public_monitoring_enabled' => true,
        'status' => false,
    ]);
    $restrictedActive = Sensor::factory()->create([
        'public_monitoring_enabled' => false,
        'status' => true,
    ]);

    $policy = app(PublicGraphVisibility::class);
    $this->assertTrue($policy->isPublic($publicOffline));
    $this->assertFalse($policy->isPublic($restrictedActive));
}
~~~

- [ ] **Step 2: Run the test before creating the service.**

Run: cd back; php artisan test --filter=PublicGraphControllerTest

Expected: failure because PublicGraphVisibility is unavailable.

- [ ] **Step 3: Implement exactly the three policy methods.**

~~~
public function isPublic(Sensor $sensor): bool
{
    return $sensor->public_monitoring_enabled === true;
}

public function publicSensorsQuery(): Builder
{
    return Sensor::query()->where('public_monitoring_enabled', true);
}

public function requirePublic(Sensor $sensor): Sensor
{
    abort_unless($this->isPublic($sensor), 404);
    return $sensor;
}
~~~

Do not add policy cache/state, a frontend flag, or any Device/Lab/status condition.

- [ ] **Step 4: Verify and commit.**

Run: cd back; php artisan test --filter=PublicGraphControllerTest; vendor/bin/pint --dirty

~~~
git add back/app/Services/Monitoring/PublicGraphVisibility.php back/tests/Feature/PublicGraphControllerTest.php
git commit -m "feat: centralize public graph visibility"
~~~

### Task 3: Replace broad anonymous REST with the graph contract

**Files:**

- Create: back/app/Http/Controllers/Api/PublicGraphController.php, back/app/Services/Monitoring/PublicGraphSeriesService.php
- Modify: back/routes/api.php, back/app/Http/Controllers/Api/DashboardController.php, back/app/Http/Controllers/API/SensorApiController.php, back/app/Http/Controllers/API/DeviceApiController.php
- Test: back/tests/Feature/PublicGraphControllerTest.php, back/tests/Feature/Phase2ApiEndpointsTest.php, back/tests/Feature/SensorApiControllerTest.php

**Interfaces:** PublicGraphController has bootstrap(PublicGraphVisibility) and series(Request, Sensor, PublicGraphVisibility, PublicGraphSeriesService). The controller calls requirePublic before the series service receives a sensor.

- [ ] **Step 1: Write public/restricted route contract tests.**

~~~
$this->getJson('/api/public/graph/bootstrap')
    ->assertOk()
    ->assertJsonPath('devices.0.sensors.0.id', $publicSensor->id)
    ->assertJsonMissingPath('devices.0.lab')
    ->assertJsonMissingPath('devices.0.status');

$this->getJson("/api/public/graph/sensors/{$restrictedSensor->id}/series?from=2026-09-09T10:00:00Z&to=2026-09-09T10:05:00Z&aggregation=raw")
    ->assertNotFound();

$this->getJson('/api/dashboard/public')->assertNotFound();
~~~

Include public-offline inclusion, guessed-ID 404, exact-window filtering, raw ordering, one-minute bucket behavior, absent bucket gaps, invalid UTC/range/aggregation 422, and raw count-bound tests.

- [ ] **Step 2: Run new and legacy tests before implementation.**

Run: cd back; php artisan test --filter=PublicGraphControllerTest --filter=Phase2ApiEndpointsTest

Expected: new routes fail; the current public dashboard leaks metrics, alert counts, labs, statuses, and unrestricted inventory.


- [ ] **Step 3: Implement minimal bootstrap and series ownership.**

The bootstrap starts at publicSensorsQuery()->with(['sensorType', 'device']), projects only frozen fields, then groups/sorts by device. The series service owns aggregation only, uses an already-authorized Sensor, and reads complete windows from the indexed `sensor_readings` table; it may not use the 120-entry Redis latest cache as a range shortcut. It never receives client Device/Lab identity and never owns visibility. Validate the frozen UTC grammar before parsing. Use the framework's `ValidationException::withMessages(...)` for the documented 422 response rather than introducing an unspecified `GraphSeriesValidationException`. Keep the bounded single-read algorithm explicit:

~~~
$readings = $sensor->readings()
    ->where('reading_time', '>=', $from)
    ->where('reading_time', '<', $to)
    ->where('reading_time', '<=', now())
    ->orderBy('reading_time')
    ->orderBy('id')
    ->limit($aggregation === 'raw' ? 2001 : 50001)
    ->get();

if ($aggregation === 'raw' && $readings->count() > 2000) {
    throw ValidationException::withMessages(['range' => 'Raw series exceeds 2,000 samples.']);
}

if ($aggregation === '1m' && $readings->count() > 50000) {
    throw ValidationException::withMessages(['range' => 'Aggregated source series exceeds 50,000 samples.']);
}
~~~

For raw results, map each reading to one point. For one-minute results, group this bounded ordered collection by the UTC minute start, calculate point mean/min/max/sample_count, set reading_id to null, and calculate response statistics from the ungrouped valid readings. Do not insert a group for a missing minute.


- [ ] **Step 4: Cut anonymous generic paths only after the public entry-point and Blade tests pass.**

Delete anonymous GET `/api/dashboard/public`, GET `/api/sensors/{sensor}/latest-readings`, GET `/api/devices/{device}/sensors`, and GET `/api/config/public`. Retain latest-reading/device-sensor handlers only behind the proven guard for current authenticated API and Blade consumers; test session-cookie and bearer-token access separately rather than assuming `auth:sanctum` covers both. Do not move `/api/alerts/active` in this task; Stage 7 owns it. The production `/dashboard` test must prove the new guest entry point works and the old Blade output cannot continue exposing broad data.

- [ ] **Step 5: Verify and commit.**

Run: cd back; php artisan test --filter=PublicGraphControllerTest --filter=SensorApiControllerTest --filter=Phase2ApiEndpointsTest; vendor/bin/pint --dirty

~~~
git add back/app/Http/Controllers back/app/Services/Monitoring back/routes/api.php back/tests/Feature
git commit -m "feat: expose only scoped public graph data"
~~~

### Task 4: Gate browser delivery after durable publication

**Files:**

- Modify: back/app/Services/Ingestion/DomainEventBroadcastConsumer.php, back/app/Console/Commands/ConsumeDomainEvents.php, back/app/Events/NewSensorReading.php
- Test: back/tests/Feature/DomainEventBroadcastConsumerTest.php, back/tests/Feature/SensorApiControllerTest.php, back/tests/Unit/EventEnvelopeTest.php

**Interfaces:** The consumer receives PublicGraphVisibility explicitly. broadcastSensorReadingCreated loads the relation, calls isPublic($reading->sensor), and dispatches NewSensorReading only for public sensors. The command and tests pass the same dependency.

- [ ] **Step 1: Write both visibility-state event tests.**

~~~
public function test_restricted_sensor_fact_is_acked_without_public_reading_event(): void
{
    Event::fake([NewSensorReading::class]);
    $outbox = $this->sensorReadingOutbox(['public_monitoring_enabled' => false]);
    $this->xadd($outbox->id, 'sensor.reading.created');

    $this->consumer()->runOnce('worker-A', 10, 100);

    Event::assertNotDispatched(NewSensorReading::class);
    $this->assertNotNull($outbox->fresh()->delivered_at);
}
~~~

Mirror it with an explicit true value and one dispatch. In SensorApiControllerTest, assert ingestion creates reading/outbox records for both visibility values.

Also replace the current legacy-payload assertion with this public transport assertion:

~~~
$data = (new NewSensorReading($reading))->broadcastWith();

$this->assertSame($reading->id, $data['reading_id']);
$this->assertSame($reading->sensor_id, $data['sensor_id']);
$this->assertArrayNotHasKey('sensor_name', $data);
$this->assertArrayNotHasKey('sensor_type', $data);
$this->assertArrayNotHasKey('unit', $data);
$this->assertArrayNotHasKey('device_name', $data);
$this->assertArrayNotHasKey('lab_name', $data);
~~~

- [ ] **Step 2: Run the consumer test before changing code.**

Run: cd back; php artisan test --filter=DomainEventBroadcastConsumerTest

Expected: the restricted event currently dispatches, proving the existing leak.

- [ ] **Step 3: Inject policy and conditionally dispatch only the already-authorized representation.**

Run Task 0's legacy-consumer proof first. Keep transaction, idempotency, delivered_at, XACK, retry, and DLQ flow unchanged. Restricted is a successful terminal delivery outcome: log safe IDs, return, commit delivered_at, and ACK. Update `NewSensorReading` comments to state the consumer already authorized its public channel, and remove sensor/device/lab metadata from its public payload because the bootstrap already supplies labels/unit. Never add a model query/policy branch inside the event or retain an enriched public fallback. Add tests for current-time policy behavior: enabling exposes the bounded series/history allowed by the current sensor policy; disabling before consumer dispatch suppresses the queued public event.

- [ ] **Step 4: Verify and commit.**

Run: cd back; php artisan test --filter=DomainEventBroadcastConsumerTest --filter=SensorApiControllerTest; vendor/bin/pint --dirty

~~~
git add back/app/Services/Ingestion/DomainEventBroadcastConsumer.php back/app/Console/Commands/ConsumeDomainEvents.php back/app/Events/NewSensorReading.php back/tests/Feature/DomainEventBroadcastConsumerTest.php back/tests/Feature/SensorApiControllerTest.php back/tests/Unit/EventEnvelopeTest.php
git commit -m "feat: gate public sensor broadcasts by visibility"
~~~

### Task 5: Cut the guest frontend over to scoped graph data

**Files:**

- Create: front/src/api/publicGraph.js, front/src/stores/sensorReadings.js, front/src/realtime/useSensorRealtime.test.js, front/src/stores/sensorReadings.test.js
- Modify: front/src/api/dashboard.js, front/src/api/sensors.js, front/src/realtime/useSensorRealtime.js, front/src/views/DashboardView.vue, front/src/components/dashboard/SensorMonitorBoard.vue

**Interfaces:** getPublicGraphBootstrap() and getPublicGraphSeries({ sensorId, from, to, aggregation, signal }) are the only guest data adapters. Pinia uses key { authorizationScope, sensorId, from, to, aggregation } and owns generation, abort, normalized merge, dedupe, pruning, last-observed presentation, and release. useSensorRealtime receives a recovery callback/series adapter instead of importing the generic public latest-reading endpoint.

- [ ] **Step 1: Write race and payload-safety unit tests.**

~~~
it('keeps the newer range when an older request resolves last', async () => {
  const store = useSensorReadingsStore();
  const older = store.hydrate(oldKey);
  const newer = store.hydrate(newKey);

  resolve(newer, fiveMinuteSeries);
  resolve(older, oneMinuteSeries);

  expect(store.seriesFor(newKey).window.from).toBe(fiveMinuteSeries.window.from);
});

it('rejects wrong-sensor and non-finite realtime payloads', () => {
  expect(normalizePublicReading({ sensor_id: 3, value: 'NaN' }, 3)).toBeNull();
  expect(normalizePublicReading({ sensor_id: 4, value: 12 }, 3)).toBeNull();
});
~~~

- [ ] **Step 2: Run tests before scoped modules exist.**

Run: cd front; npm run test:unit -- publicGraph sensorReadings useSensorRealtime

Expected: failure because the scoped graph modules are absent.

- [ ] **Step 3: Implement the two adapters and one shared projection.**

Reuse apiClient, unwrapData, singleton Echo, and channelRegistry. Normalize UTC timestamps, reject non-finite values, dedupe by reading_id, preserve ordered samples, and clear scoped state on logout/revocation. Resolve public labels/unit from bootstrap/store rather than discarded event metadata. Recovery is one request for the active immutable graph key; it is never an interval.

- [ ] **Step 4: Replace dashboard broad dependencies and delete polling.**

DashboardView loads only bootstrap; it no longer loads public metrics, config-driven interval, alerts, device status, or generic latest-reading inventory. During the Lab Blue migration remove SensorMonitorBoard MAX_POINTS, pollTimer, startPolling, stopPolling, refreshVisibleMonitors, refreshMonitor, and local-storage/server auto-save. Guests keep an in-memory public-source-only draft; Save begins authentication and never reports a server save.

- [ ] **Step 5: Verify and commit.**

Run: cd front; npm run test:unit; npm run test:realtime; npm run build

~~~
git add front/src/api front/src/stores front/src/realtime front/src/views/DashboardView.vue front/src/components/dashboard/SensorMonitorBoard.vue
git commit -m "feat: use scoped public graph projection"
~~~

### Task 6: Prove the boundary and record the Stage 7 dependency

**Files:**

- Modify: front/.audit-e2e/network-assertion.mjs, front/.audit-e2e/fixtures.mjs, PLAN.md, front_rebuild_plan/FRONT_REBUILD_PLAN_ADJUSTMENTS_v1.1.md
- Test: backend feature suites and existing Playwright audit scripts.

- [ ] **Step 1: Add a guest trace assertion for the permitted shape and UI parity.**

Permit one bootstrap, one selected series, bounded lifecycle recovery, and sensor.{id}. Fail on recurring GETs, generic latest/device/config paths, metrics, preferences, alert/event/device-status API calls or channels, a second Echo instance, or a legacy Blade dashboard response. At 320/390/768/1440, capture paired guest/auth screenshots of the graph shell: toolbar, selection, range controls, primary chart, chart list, and empty/error states must share the approved Lab Blue hierarchy; only explicitly authorized slots may differ.

- [ ] **Step 2: Run full targeted evidence.**

~~~
cd back; php artisan test --filter=PublicGraphControllerTest --filter=DomainEventBroadcastConsumerTest --filter=SensorApiControllerTest
cd ../front; npm run test:unit; npm run test:realtime; npm run build; npm run audit:network
~~~

Expected: public sources are visible/live; restricted, guessed, and default-new sources are absent/404/silent; public sources with no new samples remain visible with truthful last-observed/no-data state and no inferred device failure; no steady-state polling occurs.

- [ ] **Step 3: Inspect the actual anonymous surface and query plan.**

Run: cd back; php artisan route:list --path=api

Expected: graph bootstrap and graph series are the only anonymous product-domain routes; `/api/config/public` and generic inventory/history are gone, and `/api/iot/sensors` plus `/api/health` have their recorded non-public/operational treatment. Record the existing alert endpoint/channel as the Stage 7 blocker to the final graph-only claim until it is protected. Retain the indexed range-query plan with the evidence.

- [ ] **Step 4: Commit only reviewed evidence and report only commands actually run.**

~~~
git add front/.audit-e2e PLAN.md front_rebuild_plan
git commit -m "test: prove public graph visibility boundary"
~~~

## Acceptance Matrix

| Case | Bootstrap | Series | Public event | Durable fact |
| --- | --- | --- | --- | --- |
| Explicitly public sensor | Present | 200 | Delivered once | Created |
| Restricted sensor | Absent | 404 | Never delivered | Created |
| Guessed existing/restricted ID | Absent | 404 | Never delivered | N/A |
| Default-new sensor | Absent | 404 | Never delivered | Created when it later reports |
| Public with no current sample | Present without status leak | Allowed | No fabricated data or stale cutoff | Retained |
| Restricted → public | Appears after bootstrap refresh | Allowed | Subsequent events | Unchanged |
| Public → restricted | Disappears after bootstrap refresh | 404 | Subsequent events stop | Unchanged |

## Rollback

Reverting frontend composition never changes sensor_readings, the outbox, Redis Streams, or public_monitoring_enabled decisions. If a release must roll back after the migration, retain the column and disable the two graph routes through the normal deployment procedure; never restore broad anonymous dashboard/inventory endpoints as a fallback. Reverting consumer gating needs an explicit security review because it reopens restricted delivery.
