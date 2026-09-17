<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PENDING MAC VERIFICATION — written on Windows, never executed here.
 *
 * Task item 3: the dashboard metrics endpoint (`GET /api/dashboard/metrics`,
 * `Api\DashboardController::metrics()`) — the intended and now-implemented contract is a split
 * one, not a single yes/no:
 *   - Aggregate counts (total/active devices, total sensors, active/unresolved alerts) require NO
 *     specific permission beyond `auth:sanctum` + `verified` — this is a dashboard summary, not
 *     telemetry, matching `routes/api.php` (no `permission:*` middleware on this route).
 *   - `latest_readings` (raw reading values + sensor/device identity) is TELEMETRY, the same class
 *     of data REST reading endpoints and the private `sensor.{id}` channel gate behind
 *     `sensor_reading.view` (SEC-RT-002). Root-cause fix applied in `DashboardController::
 *     dashboardPayload()`: previously this field was embedded for ANY authenticated user with no
 *     permission check at all (not even `sensor.view`); it is now gated on `sensor_reading.view`,
 *     consistent with every other telemetry surface Fix 1 touches. The frontend does not currently
 *     consume this field at all (checked: no reference in `front/src`), so gating it has no known
 *     product impact.
 */
class DashboardMetricsTelemetryPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithPermissions(array $codes): User
    {
        $role = Role::create([
            'code' => 'dash-matrix-'.uniqid(),
            'name' => 'Dashboard Matrix Role',
            'description' => 'test',
            'is_system' => false,
            'level' => 5,
        ]);
        $permIds = \App\Models\Permission::whereIn('code', $codes)->pluck('id');
        $role->permissions()->sync($permIds);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_authenticated_user_without_any_resource_permissions_can_read_dashboard_metrics(): void
    {
        // Zero permissions assigned — deliberately weaker than "user" (which already has
        // sensor_reading.view) to prove the aggregate-metrics part of the contract needs no
        // specific permission at all.
        $user = $this->userWithPermissions([]);

        $response = $this->actingAs($user)
            ->getJson('/api/dashboard/metrics')
            ->assertOk();

        $response->assertJsonStructure([
            'total_devices',
            'active_devices',
            'total_sensors',
            'active_alerts',
            'unresolved_alerts',
        ]);
    }

    public function test_user_without_sensor_reading_view_does_not_receive_latest_readings(): void
    {
        $user = $this->userWithPermissions([]);
        $sensor = Sensor::factory()->create();
        SensorReading::factory()->create(['sensor_id' => $sensor->id, 'reading_time' => now()->subMinute()]);

        $response = $this->actingAs($user)
            ->getJson('/api/dashboard/metrics')
            ->assertOk();

        $response->assertJsonMissingPath('latest_readings');
    }

    public function test_user_with_sensor_reading_view_receives_latest_readings(): void
    {
        $user = $this->userWithPermissions(['sensor_reading.view']);
        $sensor = Sensor::factory()->create();
        SensorReading::factory()->create(['sensor_id' => $sensor->id, 'reading_time' => now()->subMinute()]);

        $response = $this->actingAs($user)
            ->getJson('/api/dashboard/metrics')
            ->assertOk();

        $response->assertJsonCount(1, 'latest_readings');
        $this->assertSame($sensor->id, $response->json('latest_readings.0.sensor.id'));
    }
}
