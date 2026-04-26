<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceApiStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_update_device_status_on_api(): void
    {
        $device = Device::factory()->create([
            'status' => true,
            'is_active' => true,
        ]);

        $this->postJson("/api/devices/{$device->id}/status", [
            'status' => false,
        ])->assertStatus(401);
    }

    public function test_admin_user_can_update_status_and_is_active_consistently(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $device = Device::factory()->create([
            'status' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson("/api/devices/{$device->id}/status", [
            'status' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('device.id', $device->id)
            ->assertJsonPath('device.status', false)
            ->assertJsonPath('device.is_active', false);

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'status' => false,
            'is_active' => false,
        ]);
    }

    public function test_update_status_ignores_unexpected_is_active_payload_and_keeps_consistency(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $device = Device::factory()->create([
            'status' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson("/api/devices/{$device->id}/status", [
            'status' => false,
            'is_active' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('device.status', false)
            ->assertJsonPath('device.is_active', false);

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'status' => false,
            'is_active' => false,
        ]);
    }

    public function test_update_status_rejects_invalid_boolean_payload(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $device = Device::factory()->create([
            'status' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->postJson("/api/devices/{$device->id}/status", [
            'status' => 'not-a-bool',
        ])->assertStatus(422);

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'status' => true,
            'is_active' => true,
        ]);
    }

    public function test_non_admin_user_cannot_update_device_status_on_api(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $device = Device::factory()->create([
            'status' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)->postJson("/api/devices/{$device->id}/status", [
            'status' => false,
        ])->assertForbidden();
    }
}
