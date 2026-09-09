<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 6 boundary: `public_monitoring_enabled` must be administrable through the existing Sensor
 * CRUD owner (never raw SQL). Column defaults FALSE and stays fail-closed unless an admin opts in.
 */
class SensorPublicMonitoringAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function availableDevice(): Device
    {
        // createSensor rejects unavailable devices with 422.
        return Device::factory()->create(['status' => true, 'is_active' => true]);
    }

    public function test_create_defaults_public_monitoring_enabled_to_false_when_omitted(): void
    {
        $device = $this->availableDevice();
        $type = SensorType::factory()->create();

        $this->actingAs($this->admin())->postJson('/api/sensors', [
            'name' => 'Sensor A',
            'device_id' => $device->id,
            'sensor_type_id' => $type->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.public_monitoring_enabled', false);

        $this->assertDatabaseHas('sensors', [
            'name' => 'Sensor A',
            'public_monitoring_enabled' => false,
        ]);
    }

    public function test_admin_can_enable_public_monitoring_on_create(): void
    {
        $device = $this->availableDevice();
        $type = SensorType::factory()->create();

        $this->actingAs($this->admin())->postJson('/api/sensors', [
            'name' => 'Sensor B',
            'device_id' => $device->id,
            'sensor_type_id' => $type->id,
            'public_monitoring_enabled' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.public_monitoring_enabled', true);

        $this->assertDatabaseHas('sensors', [
            'name' => 'Sensor B',
            'public_monitoring_enabled' => true,
        ]);
    }

    public function test_admin_can_toggle_public_monitoring_on_update(): void
    {
        $device = $this->availableDevice();
        $sensor = Sensor::factory()->create([
            'device_id' => $device->id,
            'public_monitoring_enabled' => false,
        ]);

        $base = [
            'name' => $sensor->name,
            'device_id' => $device->id,
            'sensor_type_id' => $sensor->sensor_type_id,
        ];

        $this->actingAs($this->admin())
            ->putJson("/api/sensors/{$sensor->id}", $base + ['public_monitoring_enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.public_monitoring_enabled', true);
        $this->assertTrue((bool) $sensor->fresh()->public_monitoring_enabled);

        $this->actingAs($this->admin())
            ->putJson("/api/sensors/{$sensor->id}", $base + ['public_monitoring_enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.public_monitoring_enabled', false);
        $this->assertFalse((bool) $sensor->fresh()->public_monitoring_enabled);
    }

    public function test_public_monitoring_enabled_rejects_non_boolean(): void
    {
        $device = $this->availableDevice();
        $type = SensorType::factory()->create();

        $this->actingAs($this->admin())->postJson('/api/sensors', [
            'name' => 'Sensor C',
            'device_id' => $device->id,
            'sensor_type_id' => $type->id,
            'public_monitoring_enabled' => 'banana',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('public_monitoring_enabled');
    }
}
