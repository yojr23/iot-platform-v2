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
 * SEC-RT-002 parity, task item 1: a metadata-only user (sensor.view yes, sensor_reading.view no)
 * must receive NO telemetry (readings/latest_reading) embedded in SensorResource.
 *
 * Root cause this guards: `SensorResource::toArray()` previously embedded `latest_readings`/
 * `latest_reading` unconditionally whenever the `readings`/`latestReading` relation happened to be
 * eager-loaded by the caller, regardless of the requesting user's permissions. `show()`
 * (GET /sensors/{id}, gated only on `sensor.view`) eager-loads the last 10 readings, and
 * `DeviceApiController::sensors()` (GET /devices/{device}/sensor-list, gated only on
 * `device.view`) eager-loads `latestReading` — both leaked reading telemetry through a
 * metadata-only permission gate, the same class of bug SEC-RT-002 already fixed for the dedicated
 * reading endpoints. Fixed once in the shared `SensorResource` (every caller routes through it)
 * instead of duplicating a check in each controller.
 */
class SensorResourceTelemetryVisibilityTest extends TestCase
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
            'code' => 'res-matrix-'.uniqid(),
            'name' => 'Resource Matrix Role',
            'description' => 'test',
            'is_system' => false,
            'level' => 10,
        ]);
        $permIds = Permission::whereIn('code', $codes)->pluck('id');
        $role->permissions()->sync($permIds);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_sensor_view_only_user_sees_metadata_but_no_readings_in_show(): void
    {
        $user = $this->userWithPermissions(['sensor.view']);
        $sensor = Sensor::factory()->create();
        SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'reading_time' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/sensors/'.$sensor->id)
            ->assertOk();

        // Metadata is still present — this endpoint's own gate (`sensor.view`) is unchanged.
        $response->assertJsonPath('id', $sensor->id);
        $response->assertJsonPath('name', $sensor->name);
        // Telemetry must be entirely absent, not just empty — the key itself must not appear.
        $response->assertJsonMissingPath('latest_readings');
    }

    public function test_sensor_view_and_reading_view_user_sees_readings_in_show(): void
    {
        $user = $this->userWithPermissions(['sensor.view', 'sensor_reading.view']);
        $sensor = Sensor::factory()->create();
        SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'reading_time' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/sensors/'.$sensor->id)
            ->assertOk();

        $response->assertJsonCount(1, 'latest_readings');
    }

    public function test_device_view_only_user_sees_device_sensor_list_without_latest_reading(): void
    {
        $user = $this->userWithPermissions(['device.view']);
        $sensor = Sensor::factory()->create();
        SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'reading_time' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/devices/'.$sensor->device_id.'/sensor-list')
            ->assertOk();

        // The sensor itself is visible (device.view authorizes this endpoint)...
        $response->assertJsonFragment(['id' => $sensor->id]);
        // ...but no sensor in the collection carries a latest_reading key.
        foreach ($response->json() as $item) {
            $this->assertArrayNotHasKey('latest_reading', $item);
        }
    }
}
