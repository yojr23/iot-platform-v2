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
 * Task item 2: `device.view` without `sensor_reading.view` → the device (and its sensors as
 * metadata) stays visible, but sensor TELEMETRY endpoints must 403. `device.view` and
 * `sensor_reading.view` are independent permissions in `RolePermissionSeeder` — nothing about
 * being able to see a device's sensor list implies the caller may read what those sensors
 * measured.
 */
class DeviceViewWithoutTelemetryPermissionTest extends TestCase
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
            'code' => 'device-only-'.uniqid(),
            'name' => 'Device Only Role',
            'description' => 'test',
            'is_system' => false,
            'level' => 10,
        ]);
        $permIds = Permission::whereIn('code', $codes)->pluck('id');
        $role->permissions()->sync($permIds);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_device_and_its_sensor_list_stay_visible_with_device_view_only(): void
    {
        $user = $this->userWithPermissions(['device.view']);
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/devices/'.$sensor->device_id)
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/devices/'.$sensor->device_id.'/sensor-list')
            ->assertOk()
            ->assertJsonFragment(['id' => $sensor->id]);
    }

    public function test_latest_readings_endpoint_403s_with_device_view_only(): void
    {
        $user = $this->userWithPermissions(['device.view']);
        $sensor = Sensor::factory()->create();
        SensorReading::factory()->create(['sensor_id' => $sensor->id, 'reading_time' => now()->subMinute()]);

        $this->actingAs($user)
            ->getJson('/api/sensors/'.$sensor->id.'/latest-readings')
            ->assertForbidden();
    }

    public function test_readings_endpoint_403s_with_device_view_only(): void
    {
        $user = $this->userWithPermissions(['device.view']);
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/sensors/'.$sensor->id.'/readings')
            ->assertForbidden();
    }

    public function test_export_endpoint_403s_with_device_view_only(): void
    {
        $user = $this->userWithPermissions(['device.view']);
        $sensor = Sensor::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/sensors/'.$sensor->id.'/readings/export?from=2026-01-01&to=2026-01-02')
            ->assertForbidden();
    }
}
