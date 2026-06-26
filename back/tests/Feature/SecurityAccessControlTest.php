<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_when_trying_to_access_protected_web_route(): void
    {
        $response = $this->get(route('devices.create'));

        $response->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_create_devices(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($nonAdmin)->post(route('devices.store'), [
            'name' => 'Unauthorized Device',
            'serial_number' => 'UNAUTH-001',
            'device_type_id' => \App\Models\DeviceType::factory()->create()->id,
            'lab_id' => \App\Models\Lab::factory()->create()->id,
            'ip_address' => '192.168.10.20',
            'mac_address' => 'AA:BB:CC:DD:EE:01',
            'status' => true,
        ]);

        $response->assertForbidden();

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

        $response = $this->actingAs($nonAdmin)->put(route('devices.update', $device), [
            'name' => 'Hacked Name',
            'ip_address' => $device->ip_address,
            'mac_address' => $device->mac_address,
            'lab_id' => $device->lab_id,
        ]);

        $response->assertForbidden();

        $this->assertSame('Original Name', $device->fresh()->name);
    }

    public function test_non_admin_cannot_bypass_alert_rule_creation_via_api_when_authenticated(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $sensor = \App\Models\Sensor::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/alert-rules/store', [
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
        $sensor = \App\Models\Sensor::factory()->create();

        $this->postJson('/api/alert-rules/store', [
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
