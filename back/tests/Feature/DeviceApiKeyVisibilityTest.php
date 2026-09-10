<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D6 (front_rebuild_plan/MAIN_PARITY_GAPS_PLAN.md): DeviceResource now serializes the existing
 * `api_key` column (auto-generated in Device::boot()) gated to admin requesters via `when()`.
 * GAP: could not run `php artisan test --filter=DeviceApiKeyVisibilityTest` in this session
 * (no php/composer available) — run it on an operator machine before merging.
 */
class DeviceApiKeyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_api_key_on_device_show(): void
    {
        $device = Device::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->getJson("/api/devices/{$device->id}");

        $response->assertOk()
            ->assertJsonPath('api_key', $device->api_key);
        $this->assertNotEmpty($response->json('api_key'));
    }

    public function test_non_admin_does_not_see_api_key_on_device_show(): void
    {
        $device = Device::factory()->create();
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->getJson("/api/devices/{$device->id}");

        $response->assertOk()
            ->assertJsonMissingPath('api_key');
    }

    public function test_admin_does_not_see_api_key_on_devices_index(): void
    {
        // Security: api_key is exposed only on single-device detail, never in the bulk list —
        // even for admins — to keep every device credential out of the list-page payload.
        Device::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->getJson('/api/devices?per_page=10');

        $response->assertOk();
        $this->assertArrayNotHasKey('api_key', $response->json('data.0'));
    }

    public function test_non_admin_does_not_see_api_key_on_devices_index(): void
    {
        Device::factory()->create();
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->getJson('/api/devices?per_page=10');

        $response->assertOk();
        $this->assertArrayNotHasKey('api_key', $response->json('data.0'));
    }
}
