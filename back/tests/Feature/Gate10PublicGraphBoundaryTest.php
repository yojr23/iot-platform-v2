<?php

namespace Tests\Feature;

use App\Events\NewSensorReading;
use App\Models\Device;
use App\Models\DomainEventOutbox;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SensorType;
use App\Models\User;
use App\Services\Monitoring\PublicGraphVisibility;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 10 — freezes the public-graph security boundary already implemented by
 * `PublicGraphController` / `PublicGraphVisibility` / `DomainEventBroadcastConsumer` (PLAN.md
 * Stage 6.0/6.3, Gate 6, Gate 8). This is a boundary-freeze test file, not a reimplementation:
 * it composes the existing owners (`PublicGraphVisibility::isPublic()`, the real
 * `/api/public/graph/*` routes, the real auth/broadcasting middleware stack) the same way
 * `tests/Feature/PublicGraphControllerTest.php`, `tests/Feature/DomainEventBroadcastConsumerTest.php`
 * and `tests/Feature/BroadcastChannelAuthorizationTest.php` already do, rather than opening a new
 * assertion path.
 *
 * No new production code — TEST-ONLY per the Gate 10 task. Every case below was verified against
 * the actual contract in `app/Http/Controllers/Api/PublicGraphController.php`,
 * `app/Services/Monitoring/PublicGraphVisibility.php`, `app/Events/NewSensorReading.php`,
 * `app/Services/Ingestion/DomainEventBroadcastConsumer.php` and `routes/api.php` before being
 * written; none of it invents new behavior.
 */
class Gate10PublicGraphBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function publicSensor(array $overrides = []): Sensor
    {
        $device = Device::factory()->create();

        return Sensor::factory()->create(array_merge([
            'device_id' => $device->id,
            'public_monitoring_enabled' => true,
        ], $overrides));
    }

    private function restrictedSensor(array $overrides = []): Sensor
    {
        $device = Device::factory()->create();

        return Sensor::factory()->create(array_merge([
            'device_id' => $device->id,
            'public_monitoring_enabled' => false,
        ], $overrides));
    }

    /**
     * G10-PUB-01: fail-closed by construction — a sensor created without an explicit
     * `public_monitoring_enabled` value must default to false (DB column default, migration
     * `2026_09_09_000002_add_public_monitoring_enabled_to_sensors_table.php`).
     */
    public function test_g10_pub_01_new_sensor_defaults_to_not_publicly_monitored(): void
    {
        $device = Device::factory()->create();
        $sensorType = SensorType::factory()->create();

        $sensor = Sensor::create([
            'name' => 'Freshly Provisioned Sensor',
            'device_id' => $device->id,
            'sensor_type_id' => $sensorType->id,
            // public_monitoring_enabled deliberately omitted — proving the DB/model default, not
            // an explicit false passed by the test.
        ]);

        $this->assertFalse($sensor->fresh()->public_monitoring_enabled);
    }

    /**
     * G10-PUB-02: a restricted sensor's series endpoint is a 404, not a 403/401 — guests get no
     * signal that the sensor exists at all (`PublicGraphVisibility::requirePublic()`).
     */
    public function test_g10_pub_02_restricted_sensor_series_endpoint_returns_404(): void
    {
        $sensor = $this->restrictedSensor();

        $this->getJson("/api/public/graph/sensors/{$sensor->id}/series?from=2026-09-09T00:00:00Z&to=2026-09-09T00:05:00Z")
            ->assertNotFound();
    }

    /**
     * G10-PUB-03: operational "healthy" status (sensor.status / device.status / device.is_active
     * all true) must NOT override `public_monitoring_enabled = false` — visibility is fail-closed
     * and single-owned by `PublicGraphVisibility`, never inferred from operational fields
     * (`app/Services/Monitoring/PublicGraphVisibility.php` docblock is explicit about this).
     */
    public function test_g10_pub_03_healthy_operational_status_does_not_grant_public_visibility(): void
    {
        $sensor = $this->restrictedSensor(['status' => true]);
        $sensor->device()->update(['status' => true, 'is_active' => true]);

        $this->assertFalse(app(PublicGraphVisibility::class)->isPublic($sensor->fresh()));

        $bootstrap = $this->getJson('/api/public/graph/bootstrap');
        $sensorIds = collect($bootstrap->json('devices'))->pluck('sensors')->flatten(1)->pluck('id')->all();
        $this->assertNotContains($sensor->id, $sensorIds);

        $this->getJson("/api/public/graph/sensors/{$sensor->id}/series?from=2026-09-09T00:00:00Z&to=2026-09-09T00:05:00Z")
            ->assertNotFound();
    }

    /**
     * G10-PUB-04: bootstrap's sensor set must be exactly the set `PublicGraphVisibility` selects —
     * no more, no less, regardless of how many restricted sensors/devices exist alongside them.
     */
    public function test_g10_pub_04_bootstrap_includes_only_publicgraphvisibility_selected_sensors(): void
    {
        $publicOne = $this->publicSensor();
        $publicTwo = $this->publicSensor();
        $this->restrictedSensor();
        $this->restrictedSensor();

        $expectedIds = app(PublicGraphVisibility::class)->publicSensorsQuery()->pluck('id')->sort()->values()->all();
        $this->assertSame([$publicOne->id, $publicTwo->id], $expectedIds);

        $response = $this->getJson('/api/public/graph/bootstrap');
        $actualIds = collect($response->json('devices'))
            ->pluck('sensors')->flatten(1)->pluck('id')->sort()->values()->all();

        $this->assertSame($expectedIds, $actualIds);
    }

    /**
     * G10-PUB-05: a restricted sensor's reading still goes through the real, durable write path
     * (`SensorApiController::store()` -> `SensorReadingService::createReading()`) — the row and its
     * outbox fact are created unconditionally — but the browser-delivery audience decision
     * (`DomainEventBroadcastConsumer::broadcastSensorReadingCreated()`, which asks
     * `PublicGraphVisibility::isPublic()` for `includePublicChannel`) must resolve to a
     * private-only channel for it. Composes the same two units
     * `DomainEventBroadcastConsumerTest::test_restricted_sensor_fact_is_acked_without_public_reading_event`
     * already pins down (`PublicGraphVisibility` + `NewSensorReading::broadcastOn()`), without
     * requiring a live Redis stream.
     */
    public function test_g10_pub_05_restricted_sensor_reading_is_persisted_but_never_targets_the_public_channel(): void
    {
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $sensor = $this->restrictedSensor(['device_id' => $device->id]);
        config(['app.api_key' => 'valid-key']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 33.3,
            'api_key' => 'valid-key',
        ]);

        $response->assertCreated();

        $reading = SensorReading::query()->where('sensor_id', $sensor->id)->sole();
        $this->assertNotNull($reading);

        $outbox = DomainEventOutbox::query()
            ->where('event_type', 'sensor.reading.created')
            ->where('aggregate_id', (string) $reading->id)
            ->sole();
        $this->assertNotNull($outbox);

        // Mirrors the real dispatch site's audience decision exactly
        // (DomainEventBroadcastConsumer::broadcastSensorReadingCreated).
        $isPublic = app(PublicGraphVisibility::class)->isPublic($sensor->fresh());
        $this->assertFalse($isPublic);

        $event = new NewSensorReading($reading, includePublicChannel: $isPublic, includePrivateChannel: true);
        $channel = $event->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame('private-sensor.'.$sensor->id, $channel->name);
    }

    /**
     * G10-PUB-06: a private (restricted) sensor still has a working realtime path for authorized
     * users — the private `sensor.{id}` channel authorization
     * (`routes/channels.php`) is keyed on "sensor exists" + "request is authenticated", not on
     * `public_monitoring_enabled`, so an authorized user is never blocked from a restricted
     * sensor's realtime.
     */
    public function test_g10_pub_06_authorized_user_keeps_realtime_access_to_a_restricted_sensor(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app-id',
            'broadcasting.connections.pusher.options.cluster' => 'mt1',
            'broadcasting.connections.pusher.options.host' => 'api-mt1.pusher.com',
            'broadcasting.connections.pusher.options.useTLS' => true,
        ]);
        // See BroadcastChannelAuthorizationTest — channels.php binds to whichever broadcaster was
        // booted; re-require it now that the pusher driver is configured.
        require base_path('routes/channels.php');

        $sensor = $this->restrictedSensor();
        $user = User::factory()->create();
        $token = $user->createToken('gate10-test', ['read'])->plainTextToken;

        $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-sensor.'.$sensor->id,
            'socket_id' => '1234.1234',
        ])->assertOk()->assertJsonStructure(['auth']);
    }

    /**
     * G10-PUB-07: the anonymous product surface is exactly `/api/public/graph/bootstrap` and
     * `/api/public/graph/sensors/{sensor}/series` (routes/api.php). Every other product-domain
     * route (alerts, devices, dashboard preferences, generic sensor inventory/history, admin
     * config, alert rules) requires authentication and must reject a guest before any of that
     * data is exposed.
     */
    public function test_g10_pub_07_anonymous_requests_cannot_reach_any_other_product_route(): void
    {
        $sensor = $this->restrictedSensor();

        $protectedGetRoutes = [
            '/api/alerts',
            '/api/alerts/active',
            '/api/alerts/unresolved',
            '/api/devices',
            '/api/devices/status-snapshot',
            '/api/dashboard/preferences',
            '/api/dashboard/metrics',
            '/api/sensors',
            "/api/sensors/{$sensor->id}",
            "/api/sensors/{$sensor->id}/readings",
            "/api/sensors/{$sensor->id}/latest-readings",
            '/api/config/general',
            '/api/config/alerts',
            '/api/config/system-info',
            '/api/alert-rules',
            '/api/profile',
            '/api/config/runtime',
        ];

        foreach ($protectedGetRoutes as $route) {
            $this->getJson($route)->assertUnauthorized();
        }

        // The allowed anonymous product surface, for contrast — same guest, no token.
        $publicSensor = $this->publicSensor();
        $this->getJson('/api/public/graph/bootstrap')->assertOk();
        $this->getJson("/api/public/graph/sensors/{$publicSensor->id}/series?from=2026-09-09T00:00:00Z&to=2026-09-09T00:05:00Z")
            ->assertOk();
    }

    /**
     * G10-PUB-08: logout revokes the Sanctum token at the transport level
     * (`AuthApiController::logout()` deletes `currentAccessToken()`), so a scope change (user
     * logs out) cannot leave stale authorized state reachable — the same token immediately stops
     * working, with no residual/cached response.
     */
    public function test_g10_pub_08_logout_revokes_the_token_and_leaves_no_reachable_stale_state(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('gate10-logout-test')->plainTextToken;

        $this->withToken($token)->getJson('/api/alerts')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        // Sanctum's RequestGuard memoizes the resolved user per booted app instance. Real HTTP
        // boots a fresh app (and fresh guard) per request, so the deleted token re-authenticates
        // from scratch; the shared test container does not. Forget guards to mirror real
        // per-request auth resolution — the assertion below then proves the *token* is revoked,
        // not merely that a cached user object lingers.
        $this->app['auth']->forgetGuards();

        $response = $this->withToken($token)->getJson('/api/alerts');
        $response->assertUnauthorized();
        $this->assertArrayNotHasKey('data', $response->json() ?? []);
    }
}
