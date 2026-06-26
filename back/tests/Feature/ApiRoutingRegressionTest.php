<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRoutingRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensor_list_route_works_without_double_api_prefix(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();
        Sensor::factory()->create(['device_id' => $device->id]);

        $this->actingAs($user)->getJson("/api/devices/{$device->id}/sensor-list")
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_legacy_double_api_prefixed_sensor_list_route_returns_not_found(): void
    {
        $device = Device::factory()->create();

        $this->getJson("/api/api/devices/{$device->id}/sensor-list")
            ->assertNotFound();
    }
}
