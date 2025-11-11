<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\DeviceType;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeviceCreateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_a_new_device()
    {
        $deviceType = DeviceType::factory()->create();
        $classroom = Classroom::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('devices.store'), [
            'name' => 'Test Device',
            'serial_number' => '12345ABC',
            'device_type_id' => $deviceType->id,
            'classroom_id' => $classroom->id,
            'ip_address' => '192.168.1.1',
            'mac_address' => '00:1A:2B:3C:4D:5E',
            'status' => true,
        ]);

        $response->assertRedirect(route('devices.index'));
        $this->assertDatabaseHas('devices', [
            'name' => 'Test Device',
            'serial_number' => '12345ABC',
            'device_type_id' => $deviceType->id,
            'classroom_id' => $classroom->id,
            'ip_address' => '192.168.1.1',
            'mac_address' => '00:1A:2B:3C:4D:5E',
            'status' => true,
        ]);
    }
}
