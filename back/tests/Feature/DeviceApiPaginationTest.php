<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeviceApiPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_devices_index_is_paginated(): void
    {
        $this->createDevices(30);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/devices?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 30);
    }

    public function test_devices_index_clamps_per_page_upper_bound_to_100(): void
    {
        $this->createDevices(120);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/devices?per_page=500');

        $response->assertOk()
            ->assertJsonCount(100, 'data')
            ->assertJsonPath('per_page', 100)
            ->assertJsonPath('total', 120);
    }

    public function test_devices_index_clamps_per_page_lower_bound_to_1(): void
    {
        $this->createDevices(3);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/devices?per_page=0');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 3);
    }

    private function createDevices(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Device::factory()->create([
                'name' => "Device {$i}",
                'serial_number' => sprintf('SN-API-%05d', $i),
            ]);
        }
    }
}
