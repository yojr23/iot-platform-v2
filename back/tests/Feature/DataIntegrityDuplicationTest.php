<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BACK-01 (PLAN Mac M3): device creation is owned solely by the JSON API
 * (DeviceApiController + Rule::unique). The legacy Blade write path was retired;
 * this guards the serial-number uniqueness invariant through the canonical
 * endpoint.
 */
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

        $this->actingAs($admin)
            ->postJson('/api/devices', [
                'name' => 'Duplicate Serial Device',
                'serial_number' => 'DUP-0001',
                'device_type_id' => $deviceType->id,
                'lab_id' => $lab->id,
                'ip_address' => '192.168.1.40',
                'mac_address' => 'AA:BB:CC:DD:EE:10',
                'status' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['serial_number']);

        $this->assertSame(1, Device::query()->where('serial_number', 'DUP-0001')->count());
    }
}
