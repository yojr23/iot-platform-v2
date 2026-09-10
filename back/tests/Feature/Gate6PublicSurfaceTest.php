<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 6 Task 6.1 — the anonymous product-data bypasses are closed. `config/public` and
 * `dashboard/public` no longer exist as routes (404), `devices/{id}/sensors` is gone in favour of
 * the single authenticated owner `devices/{id}/sensor-list`, and `sensors/{id}/latest-readings`
 * moved under `auth:sanctum` (401 for guests, 200 for an authenticated caller). The public graph
 * bootstrap stays anonymous; a restricted sensor's series stays fail-closed (404).
 */
class Gate6PublicSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_config_public_route_no_longer_exists(): void
    {
        $this->getJson('/api/config/public')->assertNotFound();
    }

    public function test_guest_dashboard_public_route_no_longer_exists(): void
    {
        $this->getJson('/api/dashboard/public')->assertNotFound();
    }

    public function test_guest_device_sensors_route_no_longer_exists(): void
    {
        $device = Device::factory()->create();

        $this->getJson("/api/devices/{$device->id}/sensors")->assertNotFound();
    }

    public function test_guest_latest_readings_requires_authentication(): void
    {
        $sensor = Sensor::factory()->create(['public_monitoring_enabled' => false]);

        $this->getJson("/api/sensors/{$sensor->id}/latest-readings")->assertUnauthorized();
    }

    public function test_authenticated_latest_readings_returns_ok(): void
    {
        $sensor = Sensor::factory()->create();
        SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 21.5,
            'reading_time' => now()->subMinute(),
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/sensors/{$sensor->id}/latest-readings?limit=1")
            ->assertOk();
    }

    public function test_authenticated_device_sensor_list_returns_ok(): void
    {
        $device = Device::factory()->create();
        Sensor::factory()->create(['device_id' => $device->id]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/devices/{$device->id}/sensor-list")
            ->assertOk();
    }

    public function test_guest_public_graph_bootstrap_stays_anonymous(): void
    {
        $this->getJson('/api/public/graph/bootstrap')->assertOk();
    }

    public function test_guest_restricted_graph_series_is_not_found(): void
    {
        $sensor = Sensor::factory()->create(['public_monitoring_enabled' => false]);

        $this->getJson(
            "/api/public/graph/sensors/{$sensor->id}/series?from=2026-09-09T00:00:00Z&to=2026-09-09T00:05:00Z"
        )->assertNotFound();
    }
}
