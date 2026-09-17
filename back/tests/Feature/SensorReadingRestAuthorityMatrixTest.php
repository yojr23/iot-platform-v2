<?php

namespace Tests\Feature;

use App\Models\Permission;
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
 * Fix 1 (SEC-RT-002): the effective REST authority matrix for `latest-readings`, `readings` and
 * `export`, exercised as real HTTP requests (not just the `ResourceAccessService`/policy unit
 * matrix already covered by `SensorTelemetryAuthorityParityTest`). Confirms `sensor_reading.view`
 * alone is both necessary and sufficient for `latest-readings`/`readings`, and `sensor.view` alone
 * is NOT sufficient for any of the three — matching the private `sensor.{id}` WebSocket channel's
 * authority (`ResourceAccessService::canViewSensorReadings()`).
 *
 * Documented exception (not a divergence bug): `readings/export` additionally requires the
 * separate, admin-only `sensor_reading.export` permission at the ROUTE level
 * (`routes/api.php`: `permission:sensor_reading.export`), on top of the controller-level
 * `sensor_reading.view` check (`SensorApiController::exportReadings()` calls
 * `$this->authorize('viewReading', $sensor)`). That is a strict superset of the WS/REST telemetry
 * boundary (SEC-EXPORT-001 bulk-export policy), not a weaker or divergent one, so it is asserted
 * explicitly here rather than assumed away.
 */
class SensorReadingRestAuthorityMatrixTest extends TestCase
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
            'code' => 'rest-matrix-'.uniqid(),
            'name' => 'REST Matrix Role',
            'description' => 'test',
            'is_system' => false,
            'level' => 10,
        ]);
        $permIds = Permission::whereIn('code', $codes)->pluck('id');
        $role->permissions()->sync($permIds);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function sensorWithReading(): Sensor
    {
        $sensor = Sensor::factory()->create();
        SensorReading::factory()->create(['sensor_id' => $sensor->id, 'reading_time' => now()->subMinute()]);

        return $sensor;
    }

    public function test_sensor_reading_view_alone_can_call_latest_readings(): void
    {
        $user = $this->userWithPermissions(['sensor_reading.view']);
        $sensor = $this->sensorWithReading();

        $this->actingAs($user)
            ->getJson('/api/sensors/'.$sensor->id.'/latest-readings')
            ->assertOk();
    }

    public function test_sensor_reading_view_alone_can_call_readings(): void
    {
        $user = $this->userWithPermissions(['sensor_reading.view']);
        $sensor = $this->sensorWithReading();

        $this->actingAs($user)
            ->getJson('/api/sensors/'.$sensor->id.'/readings')
            ->assertOk();
    }

    public function test_sensor_view_alone_gets_403_on_latest_readings(): void
    {
        $user = $this->userWithPermissions(['sensor.view']);
        $sensor = $this->sensorWithReading();

        $this->actingAs($user)
            ->getJson('/api/sensors/'.$sensor->id.'/latest-readings')
            ->assertForbidden();
    }

    public function test_sensor_view_alone_gets_403_on_readings(): void
    {
        $user = $this->userWithPermissions(['sensor.view']);
        $sensor = $this->sensorWithReading();

        $this->actingAs($user)
            ->getJson('/api/sensors/'.$sensor->id.'/readings')
            ->assertForbidden();
    }

    public function test_sensor_view_alone_gets_403_on_export(): void
    {
        $user = $this->userWithPermissions(['sensor.view']);
        $sensor = $this->sensorWithReading();

        $this->actingAs($user)
            ->getJson('/api/sensors/'.$sensor->id.'/readings/export?from=2026-01-01&to=2026-01-02')
            ->assertForbidden();
    }

    public function test_sensor_reading_view_alone_without_export_permission_still_403s_on_export(): void
    {
        // Documented exception: export requires the additional, stricter sensor_reading.export
        // permission at the route level — sensor_reading.view alone (unlike latest-readings and
        // readings) is not sufficient here. This pins down the current, intentional behavior.
        $user = $this->userWithPermissions(['sensor_reading.view']);
        $sensor = $this->sensorWithReading();

        $this->actingAs($user)
            ->getJson('/api/sensors/'.$sensor->id.'/readings/export?from=2026-01-01&to=2026-01-02')
            ->assertForbidden();
    }

    public function test_sensor_reading_view_and_export_together_can_call_export(): void
    {
        $user = $this->userWithPermissions(['sensor_reading.view', 'sensor_reading.export']);
        $sensor = $this->sensorWithReading();

        $this->actingAs($user)
            ->getJson('/api/sensors/'.$sensor->id.'/readings/export?from=2026-01-01&to=2026-01-02')
            ->assertOk();
    }
}
