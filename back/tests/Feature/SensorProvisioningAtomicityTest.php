<?php

namespace Tests\Feature;

use App\Models\DeviceSensorMapping;
use App\Models\Sensor;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * SEC-TX-003 (Phase B2): a sensor and its initial canonical DeviceSensorMapping must commit together.
 * If the mapping insert fails, the sensor must NOT persist — otherwise the normalizer cannot resolve
 * a sensor that exists but has no canonical mapping (silent ingestion gap).
 */
class SensorProvisioningAtomicityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function adminToken(): string
    {
        $user = User::factory()->create(['is_admin' => true]);

        return $user->createToken('t', ['*'])->plainTextToken;
    }

    public function test_sensor_rolls_back_when_canonical_mapping_fails(): void
    {
        // Force the mapping insert to fail after the sensor is created.
        DeviceSensorMapping::creating(function (): void {
            throw new RuntimeException('forced mapping failure');
        });

        $device = \App\Models\Device::factory()->create(['status' => true, 'is_active' => true]);

        $response = $this->withToken($this->adminToken())->postJson('/api/sensors', [
            'name' => 'Atomic Sensor',
            'device_id' => $device->id,
            'sensor_type_id' => \App\Models\SensorType::factory()->create()->id,
        ]);

        $response->assertStatus(500);
        $this->assertDatabaseCount('sensors', 0);
        $this->assertDatabaseCount('device_sensor_mappings', 0);
    }

    public function test_sensor_and_mapping_persist_together_on_success(): void
    {
        $device = \App\Models\Device::factory()->create(['status' => true, 'is_active' => true]);

        $response = $this->withToken($this->adminToken())->postJson('/api/sensors', [
            'name' => 'Good Sensor',
            'device_id' => $device->id,
            'sensor_type_id' => \App\Models\SensorType::factory()->create()->id,
        ]);

        $response->assertStatus(201);
        $sensor = Sensor::firstOrFail();
        $this->assertDatabaseHas('device_sensor_mappings', [
            'sensor_id' => $sensor->id,
            'device_id' => $device->id,
            'is_active' => true,
        ]);
    }
}
