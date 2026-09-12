<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-BOLA-002 / SEC-CONFIG-001: standard users must not receive internal device
 * network topology (ip_address, mac_address, serial_number, api_key) or full nested
 * Eloquent model dumps (device_type/lab). Admins get the network metadata; api_key
 * stays gated behind the existing D6 admin+single-device reveal regardless of role.
 */
class ResourcePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_user_device_show_omits_network_topology_and_api_key(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/devices/{$device->id}");

        $response->assertOk();
        $response->assertJsonMissingPath('ip_address');
        $response->assertJsonMissingPath('mac_address');
        $response->assertJsonMissingPath('serial_number');
        $response->assertJsonMissingPath('api_key');
    }

    public function test_admin_device_show_includes_network_topology(): void
    {
        // Task 5 scope is topology minimization. The api_key reveal policy is the
        // existing D6 contract (admin-on-detail only) covered by
        // DeviceApiKeyVisibilityTest; hardening it further is Task 8 (SEC-MODEL-001).
        $admin = User::factory()->create(['is_admin' => true]);
        $device = Device::factory()->create();

        $response = $this->actingAs($admin)->getJson("/api/devices/{$device->id}");

        $response->assertOk();
        $response->assertJsonPath('ip_address', $device->ip_address);
        $response->assertJsonPath('mac_address', $device->mac_address);
        $response->assertJsonPath('serial_number', $device->serial_number);
    }

    public function test_device_show_nested_device_type_and_lab_are_minimal_projections(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/devices/{$device->id}");

        $response->assertOk();
        $response->assertJsonPath('device_type.id', $device->device_type_id);
        $response->assertJsonPath('lab.id', $device->lab_id);
        $response->assertJsonMissingPath('device_type.created_at');
        $response->assertJsonMissingPath('device_type.description');
        $response->assertJsonMissingPath('lab.created_at');
        $response->assertJsonMissingPath('lab.area');
    }

    public function test_device_model_to_array_hides_api_key(): void
    {
        $device = Device::factory()->create();

        $this->assertArrayNotHasKey('api_key', $device->toArray());
    }
}
