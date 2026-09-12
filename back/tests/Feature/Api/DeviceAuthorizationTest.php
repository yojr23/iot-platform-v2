<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-BOLA-002: private device reads are centralized behind DevicePolicy::view()/viewAny(), which
 * delegate to ResourceAccessService. Same documented rule as SensorAuthorizationTest: any verified
 * authenticated user may read (no lab/ownership model yet); guests are rejected, unverified users
 * are forbidden.
 */
class DeviceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_device_show(): void
    {
        $device = Device::factory()->create();

        $this->getJson("/api/devices/{$device->id}")->assertUnauthorized();
    }

    public function test_unverified_user_cannot_view_device_show(): void
    {
        $user = User::factory()->unverified()->create();
        $device = Device::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/devices/{$device->id}")
            ->assertForbidden();
    }

    public function test_verified_user_can_view_device_show(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/devices/{$device->id}")
            ->assertOk();
    }

    public function test_unverified_user_cannot_view_device_sensors(): void
    {
        $user = User::factory()->unverified()->create();
        $device = Device::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/devices/{$device->id}/sensor-list")
            ->assertForbidden();
    }

    public function test_verified_user_can_view_device_sensors(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();

        $this->actingAs($user)
            ->getJson("/api/devices/{$device->id}/sensor-list")
            ->assertOk();
    }

    public function test_guest_cannot_view_device_index(): void
    {
        $this->getJson('/api/devices')->assertUnauthorized();
    }

    public function test_unverified_user_cannot_view_device_index(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->getJson('/api/devices')
            ->assertForbidden();
    }

    public function test_verified_user_can_view_device_index(): void
    {
        $user = User::factory()->create();
        Device::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/devices')
            ->assertOk();
    }

    public function test_unverified_user_cannot_view_device_status_snapshot(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->getJson('/api/devices/status-snapshot')
            ->assertForbidden();
    }

    public function test_verified_user_can_view_device_status_snapshot(): void
    {
        $user = User::factory()->create();
        Device::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/devices/status-snapshot')
            ->assertOk();
    }
}
