<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Services\Ingestion\SensorReadingService;
use App\Services\Monitoring\PublicGraphVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN.md Stage 6.0 — proves the fail-closed public graph boundary: bootstrap/series both go
 * through `PublicGraphVisibility`, never sensor/device operational fields.
 *
 * Existing code reused: `SensorReadingService::createReading()` for realistic write-path
 * timestamps (same normalization the real ingestion controller uses), `Sensor`/`SensorType`/
 * `Device` factories.
 */
class PublicGraphControllerTest extends TestCase
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

    public function test_bootstrap_is_empty_with_null_default_sensor_when_no_public_sensor_exists(): void
    {
        $this->restrictedSensor();

        $response = $this->getJson('/api/public/graph/bootstrap');

        $response->assertOk()
            ->assertJson([
                'version' => 1,
                'default_sensor_id' => null,
                'devices' => [],
            ]);
    }

    public function test_bootstrap_includes_only_explicitly_public_sensors_grouped_by_device(): void
    {
        $sensorType = SensorType::factory()->create(['unit' => '°C']);
        $public = $this->publicSensor(['sensor_type_id' => $sensorType->id, 'name' => 'Public Sensor']);
        $this->restrictedSensor(['name' => 'Restricted Sensor']);

        $response = $this->getJson('/api/public/graph/bootstrap');

        $response->assertOk()
            ->assertJsonPath('default_sensor_id', $public->id)
            ->assertJsonCount(1, 'devices')
            ->assertJsonPath('devices.0.id', $public->device_id)
            ->assertJsonCount(1, 'devices.0.sensors')
            ->assertJsonPath('devices.0.sensors.0.id', $public->id)
            ->assertJsonPath('devices.0.sensors.0.name', 'Public Sensor')
            ->assertJsonPath('devices.0.sensors.0.unit', '°C')
            ->assertJsonMissingPath('devices.0.status')
            ->assertJsonMissingPath('devices.0.lab');
    }

    public function test_bootstrap_omits_devices_that_have_zero_public_sensors(): void
    {
        $restrictedOnlyDevice = Device::factory()->create();
        Sensor::factory()->create([
            'device_id' => $restrictedOnlyDevice->id,
            'public_monitoring_enabled' => false,
        ]);
        $public = $this->publicSensor();

        $response = $this->getJson('/api/public/graph/bootstrap');

        $deviceIds = collect($response->json('devices'))->pluck('id')->all();

        $this->assertSame([$public->device_id], $deviceIds);
    }

    /**
     * Mirrors PublicGraphVisibility's own unit-level guarantee at the REST boundary: an
     * operationally "active" sensor/device with the flag off stays fully absent from the guest
     * surface, and a "disabled" sensor/device with the flag on is still fully present.
     */
    public function test_operational_status_change_alone_does_not_grant_or_revoke_public_visibility(): void
    {
        $activeButRestricted = $this->restrictedSensor(['status' => true]);
        $activeButRestricted->device()->update(['status' => true, 'is_active' => true]);

        $offlineButPublic = $this->publicSensor(['status' => false]);
        $offlineButPublic->device()->update(['status' => false, 'is_active' => false]);

        $response = $this->getJson('/api/public/graph/bootstrap');
        $sensorIds = collect($response->json('devices'))->pluck('sensors')->flatten(1)->pluck('id')->all();

        $this->assertNotContains($activeButRestricted->id, $sensorIds);
        $this->assertContains($offlineButPublic->id, $sensorIds);

        $this->getJson("/api/public/graph/sensors/{$activeButRestricted->id}/series?from=2026-09-09T00:00:00Z&to=2026-09-09T00:05:00Z")
            ->assertNotFound();
        $this->getJson("/api/public/graph/sensors/{$offlineButPublic->id}/series?from=2026-09-09T00:00:00Z&to=2026-09-09T00:05:00Z")
            ->assertOk();
    }

    public function test_series_returns_404_for_restricted_sensor(): void
    {
        $sensor = $this->restrictedSensor();

        $response = $this->getJson("/api/public/graph/sensors/{$sensor->id}/series?from=2026-09-09T00:00:00Z&to=2026-09-09T00:05:00Z");

        $response->assertNotFound();
    }

    public function test_series_returns_404_for_guessed_nonexistent_sensor_id(): void
    {
        $response = $this->getJson('/api/public/graph/sensors/999999/series?from=2026-09-09T00:00:00Z&to=2026-09-09T00:05:00Z');

        $response->assertNotFound();
    }

    public function test_series_rejects_malformed_timestamp_grammar(): void
    {
        $sensor = $this->publicSensor();

        $response = $this->getJson("/api/public/graph/sensors/{$sensor->id}/series?from=2026-09-09&to=2026-09-09T00:05:00Z");

        $response->assertStatus(422);
    }

    public function test_series_rejects_from_not_before_to(): void
    {
        $sensor = $this->publicSensor();

        $response = $this->getJson("/api/public/graph/sensors/{$sensor->id}/series?from=2026-09-09T00:05:00Z&to=2026-09-09T00:05:00Z");

        $response->assertStatus(422);
    }

    public function test_series_returns_ordered_points_and_stats_within_the_half_open_window(): void
    {
        $sensor = $this->publicSensor();
        $service = app(SensorReadingService::class);
        $windowStart = CarbonImmutable::parse('2026-09-09T00:00:00Z');

        $service->createReading($sensor, 10.0, $windowStart->addSeconds(30));
        $service->createReading($sensor, 20.0, $windowStart->addSeconds(60));
        // Outside the window (>= to) — must be excluded.
        $service->createReading($sensor, 999.0, $windowStart->addSeconds(600));

        $response = $this->getJson(
            "/api/public/graph/sensors/{$sensor->id}/series?from=2026-09-09T00:00:00Z&to=2026-09-09T00:05:00Z"
        );

        $response->assertOk();
        $points = $response->json('points');

        $this->assertCount(2, $points);
        $this->assertSame(10.0, $points[0]['value']);
        $this->assertSame(20.0, $points[1]['value']);
        $this->assertSame(10.0, $response->json('stats.min'));
        $this->assertSame(20.0, $response->json('stats.max'));
        $this->assertSame(15.0, $response->json('stats.mean'));
        $this->assertSame(2, $response->json('stats.count'));
    }

    /**
     * PLAN.md Stage 6.3: the legacy 60/120-point latest-reading cache cap must never leak into
     * graph-series statistics. A 5-minute window with 151 two-second samples proves the full
     * valid source set is used, not a truncated cache-sized slice.
     */
    public function test_series_stats_are_not_capped_at_the_legacy_sixty_point_cache_limit(): void
    {
        $sensor = $this->publicSensor();
        $service = app(SensorReadingService::class);
        $windowStart = CarbonImmutable::parse('2026-09-09T00:00:00Z');

        for ($i = 0; $i <= 150; $i++) {
            $service->createReading($sensor, (float) $i, $windowStart->addSeconds($i * 2));
        }

        $response = $this->getJson(
            "/api/public/graph/sensors/{$sensor->id}/series?from=2026-09-09T00:00:00Z&to=2026-09-09T00:05:03Z"
        );

        $response->assertOk();
        $this->assertCount(151, $response->json('points'));
        $this->assertSame(151, $response->json('stats.count'));
        $this->assertSame(0.0, $response->json('stats.min'));
        $this->assertSame(150.0, $response->json('stats.max'));
    }

    public function test_public_graph_visibility_service_never_infers_from_operational_status(): void
    {
        $publicOffline = $this->publicSensor(['status' => false]);
        $restrictedActive = $this->restrictedSensor(['status' => true]);

        $policy = app(PublicGraphVisibility::class);

        $this->assertTrue($policy->isPublic($publicOffline));
        $this->assertFalse($policy->isPublic($restrictedActive));
    }
}
