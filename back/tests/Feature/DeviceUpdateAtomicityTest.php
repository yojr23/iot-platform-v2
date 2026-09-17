<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceStatusLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * SEC-TX-004 (Phase B3): a device PUT that changes metadata AND status is one aggregate mutation.
 * If the status transition fails, the metadata change must roll back too — the request returns 500,
 * so the persisted row must not reflect a partially-applied update.
 */
class DeviceUpdateAtomicityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function adminToken(): string
    {
        return User::factory()->create(['is_admin' => true])->createToken('t', ['*'])->plainTextToken;
    }

    public function test_metadata_change_rolls_back_when_status_transition_fails(): void
    {
        $device = Device::factory()->create(['status' => false, 'is_active' => false, 'name' => 'Original']);

        // Force the status transition (which writes a status log) to fail.
        DeviceStatusLog::creating(function (): void {
            throw new RuntimeException('forced status-transition failure');
        });

        $response = $this->withToken($this->adminToken())->putJson("/api/devices/{$device->id}", [
            'name' => 'Renamed',
            'serial_number' => $device->serial_number,
            'device_type_id' => $device->device_type_id,
            'lab_id' => $device->lab_id,
            'status' => true,
        ]);

        $response->assertStatus(500);
        // Metadata must NOT have persisted because the whole PUT failed.
        $this->assertSame('Original', $device->fresh()->name);
        $this->assertFalse((bool) $device->fresh()->status);
    }

    public function test_metadata_and_status_persist_together_on_success(): void
    {
        $device = Device::factory()->create(['status' => false, 'is_active' => false, 'name' => 'Original']);

        $response = $this->withToken($this->adminToken())->putJson("/api/devices/{$device->id}", [
            'name' => 'Renamed',
            'serial_number' => $device->serial_number,
            'device_type_id' => $device->device_type_id,
            'lab_id' => $device->lab_id,
            'status' => true,
        ]);

        $response->assertOk();
        $fresh = $device->fresh();
        $this->assertSame('Renamed', $fresh->name);
        $this->assertTrue((bool) $fresh->status);
    }
}
