<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeviceApiPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_devices_index_is_paginated(): void
    {
        Device::factory()->count(30)->create();

        $response = $this->getJson('/api/devices?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 30);
    }
}
