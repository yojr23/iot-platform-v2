<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceShowStatusLogsTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_show_includes_bounded_newest_first_status_logs(): void
    {
        $device = Device::factory()->create();

        $older = DeviceStatusLog::factory()->create([
            'device_id' => $device->id,
            'status' => true,
            'changed_at' => now()->subMinutes(30),
        ]);
        $newer = DeviceStatusLog::factory()->create([
            'device_id' => $device->id,
            'status' => false,
            'changed_at' => now()->subMinutes(5),
        ]);
        $newest = DeviceStatusLog::factory()->create([
            'device_id' => $device->id,
            'status' => true,
            'changed_at' => now(),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/devices/{$device->id}");

        $response->assertOk()
            ->assertJsonPath('status_logs.0.id', $newest->id)
            ->assertJsonPath('status_logs.1.id', $newer->id)
            ->assertJsonPath('status_logs.2.id', $older->id)
            ->assertJsonCount(3, 'status_logs');
    }

    public function test_device_show_status_logs_are_capped_at_twenty(): void
    {
        $device = Device::factory()->create();

        DeviceStatusLog::factory()->count(25)->sequence(fn ($sequence) => [
            'changed_at' => now()->subMinutes(25 - $sequence->index),
        ])->create(['device_id' => $device->id]);

        $user = User::factory()->create();

        $this->actingAs($user)->getJson("/api/devices/{$device->id}")
            ->assertOk()
            ->assertJsonCount(20, 'status_logs');
    }
}
