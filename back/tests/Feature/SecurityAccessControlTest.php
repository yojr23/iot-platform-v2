<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Lab;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BACK-01 (PLAN Mac M3): product mutation is owned solely by the permission-gated
 * JSON API (legacy Blade product CRUD retired). These guard that a guest and a
 * non-privileged authenticated user cannot create/update devices or alert rules
 * through the canonical endpoints.
 */
class SecurityAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_protected_device_api(): void
    {
        // Unauthenticated device create is rejected before any permission check.
        $this->postJson('/api/devices', [
            'name' => 'Guest Device',
            'serial_number' => 'GUEST-001',
        ])->assertStatus(401);
    }

    public function test_non_admin_cannot_create_devices(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);

        $this->actingAs($nonAdmin)->postJson('/api/devices', [
            'name' => 'Unauthorized Device',
            'serial_number' => 'UNAUTH-001',
            'device_type_id' => DeviceType::factory()->create()->id,
            'lab_id' => Lab::factory()->create()->id,
            'ip_address' => '192.168.10.20',
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'status' => true,
        ])->assertForbidden();

        $this->assertDatabaseMissing('devices', [
            'serial_number' => 'UNAUTH-001',
        ]);
    }

    public function test_non_admin_cannot_update_devices(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $device = Device::factory()->create([
            'name' => 'Original Name',
        ]);

        $this->actingAs($nonAdmin)->putJson("/api/devices/{$device->id}", [
            'name' => 'Hacked Name',
            'serial_number' => $device->serial_number,
            'device_type_id' => $device->device_type_id,
            'ip_address' => $device->ip_address,
            'mac_address' => $device->mac_address,
            'lab_id' => $device->lab_id,
        ])->assertForbidden();

        $this->assertSame('Original Name', $device->fresh()->name);
    }

    public function test_non_admin_cannot_bypass_alert_rule_creation_via_api_when_authenticated(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $sensor = Sensor::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/alert-rules', [
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => $sensor->device_id,
            'sensor_id' => $sensor->id,
            'min_value' => 10,
            'max_value' => 50,
            'severity' => 'warning',
            'message' => 'Bypass attempt',
            'name' => 'Bypass attempt',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('alert_rules', 0);
    }

    public function test_guest_cannot_access_alert_rule_creation_api_endpoint(): void
    {
        $sensor = Sensor::factory()->create();

        $this->postJson('/api/alert-rules', [
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => $sensor->device_id,
            'sensor_id' => $sensor->id,
            'min_value' => 10,
            'max_value' => 50,
            'severity' => 'warning',
            'message' => 'Guest bypass attempt',
            'name' => 'Guest bypass attempt',
        ])->assertStatus(401);
    }
}
