<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataIntegrityDuplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_store_rejects_duplicate_serial_number(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $deviceType = DeviceType::factory()->create();
        $lab = Lab::factory()->create();

        Device::factory()->create([
            'serial_number' => 'DUP-0001',
            'device_type_id' => $deviceType->id,
            'lab_id' => $lab->id,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('devices.create'))
            ->post(route('devices.store'), [
                'name' => 'Duplicate Serial Device',
                'serial_number' => 'DUP-0001',
                'device_type_id' => $deviceType->id,
                'lab_id' => $lab->id,
                'ip_address' => '192.168.1.40',
                'mac_address' => 'AA:BB:CC:DD:EE:10',
                'status' => true,
            ]);

        $response->assertRedirect(route('devices.create'));
        $response->assertSessionHasErrors(['serial_number']);

        $this->assertSame(1, Device::query()->where('serial_number', 'DUP-0001')->count());
    }
}
