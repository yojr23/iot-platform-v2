<?php

namespace Tests\Unit;

use App\Models\DeviceStatusLog;
use App\Models\DeviceType;
use App\Models\Lab;
use App\Services\DeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_device_creates_initial_status_log(): void
    {
        $service = new DeviceService();

        $deviceType = DeviceType::factory()->create();
        $lab = Lab::factory()->create();

        $device = $service->createDevice([
            'name' => 'Core Device',
            'serial_number' => 'CORE-0001',
            'device_type_id' => $deviceType->id,
            'lab_id' => $lab->id,
            'status' => false,
            'ip_address' => '192.168.10.15',
            'mac_address' => '00:11:22:33:44:55',
        ]);

        $this->assertNotNull($device);
        $this->assertSame('Core Device', $device->name);

        $log = DeviceStatusLog::query()->where('device_id', $device->id)->first();

        $this->assertNotNull($log);
        $this->assertFalse((bool) $log->status);
        $this->assertNotNull($log->changed_at);
    }

    public function test_create_device_returns_null_when_persistence_fails(): void
    {
        $service = new DeviceService();

        $device = $service->createDevice([
            'name' => 'Incomplete Device',
        ]);

        $this->assertNull($device);
    }
}
