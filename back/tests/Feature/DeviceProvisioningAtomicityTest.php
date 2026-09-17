<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceStatusLog;
use App\Models\DeviceType;
use App\Models\Lab;
use App\Services\DeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * SEC-TX-001 (Phase B1): device provisioning writes a Device row AND its initial status log.
 * Those must commit as one transaction — a failure after the Device insert but before the status
 * log must leave NO device (and therefore no orphan credential/hash), not a half-provisioned device.
 */
class DeviceProvisioningAtomicityTest extends TestCase
{
    use RefreshDatabase;

    /** Force the initial status-log insert to fail, deterministically, mid-provisioning. */
    private function breakStatusLogInsert(): void
    {
        DeviceStatusLog::creating(function (): void {
            throw new RuntimeException('forced status-log failure');
        });
    }

    /** @return array<string,mixed> valid device payload with satisfied FKs */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Atomic Device',
            'serial_number' => 'ATX-'.uniqid(),
            'device_type_id' => DeviceType::factory()->create()->id,
            'lab_id' => Lab::factory()->create()->id,
            'status' => true,
        ], $overrides);
    }

    public function test_service_create_rolls_back_device_when_status_log_fails(): void
    {
        $this->breakStatusLogInsert();

        try {
            app(DeviceService::class)->createDevice($this->validPayload());
            $this->fail('Expected provisioning to throw');
        } catch (RuntimeException $e) {
            // expected
        }

        $this->assertDatabaseCount('devices', 0);
        $this->assertDatabaseCount('device_status_logs', 0);
    }

    public function test_service_create_persists_device_and_status_log_on_success(): void
    {
        $device = app(DeviceService::class)->createDevice($this->validPayload([
            'name' => 'Good Device',
        ]));

        $this->assertDatabaseHas('devices', ['id' => $device->id]);
        $this->assertDatabaseHas('device_status_logs', ['device_id' => $device->id, 'status' => true]);
    }
}
