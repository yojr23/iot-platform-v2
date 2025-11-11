<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Device;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeviceUpdateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_updates_device_information()
    {
        $classroom = Classroom::factory()->create();
        $device = Device::factory()->create([
            'name' => 'Old Name',
            'ip_address' => '192.168.0.1',
            'mac_address' => '00:1A:2B:3C:4D:5E',
            'classroom_id' => $classroom->id,
        ]);

        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->put(route('devices.update', $device->id), [
            'name' => 'New Name',
            'ip_address' => '192.168.0.2',
            'mac_address' => '00:1A:2B:3C:4D:5F',
            'classroom_id' => $classroom->id,
        ]);

        $response->assertRedirect(route('devices.index'));
        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'name' => 'New Name',
            'ip_address' => '192.168.0.2',
            'mac_address' => '00:1A:2B:3C:4D:5F',
            'classroom_id' => $classroom->id,
        ]);
    }
}
