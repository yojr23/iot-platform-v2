<?php

namespace Tests\Unit;

use App\Models\DeviceStatusLog;
use App\Models\DeviceType;
use App\Models\Lab;
use App\Services\DeviceService;
use Illuminate\Database\QueryException;
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

    /**
     * `createDevice()` never actually receives incomplete data from a real caller —
     * `DeviceController::store()` validates `serial_number` as `required` before calling the
     * service, and catches `Throwable` around the call to turn a persistence failure into a
     * flashed error redirect. There is no null-check on the returned `Device` anywhere in that
     * caller, so making the service swallow the failure and return `null` here would just move the
     * crash to `$device->id` in the controller instead of fixing anything. The correct contract is
     * "let the persistence exception surface", which the controller already handles.
     */
    public function test_create_device_throws_when_required_column_is_missing(): void
    {
        $service = new DeviceService();

        $this->expectException(QueryException::class);

        $service->createDevice([
            'name' => 'Incomplete Device',
        ]);
    }
}
