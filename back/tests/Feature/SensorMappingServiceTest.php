<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceSensorMapping;
use App\Models\Sensor;
use App\Services\SensorMappingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensorMappingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_key_can_keep_multiple_non_overlapping_mapping_intervals(): void
    {
        $device = Device::factory()->create();
        $firstSensor = Sensor::factory()->create(['device_id' => $device->id]);
        $secondSensor = Sensor::factory()->create(['device_id' => $device->id]);
        $service = app(SensorMappingService::class);

        $first = $service->mapSensor($device, $firstSensor, 'ingestion_service', 'temperature');
        $second = $service->mapSensor($device, $secondSensor, 'ingestion_service', 'temperature');

        $this->assertSame(2, DeviceSensorMapping::query()
            ->where('device_id', $device->id)
            ->where('source', 'ingestion_service')
            ->where('external_key', 'temperature')
            ->count());
        $this->assertFalse($first->fresh()->is_active);
        $this->assertNotNull($first->fresh()->valid_until);
        $this->assertTrue($second->is_active);
        $this->assertNull($second->valid_until);
    }

    public function test_historical_lookup_returns_an_inactive_interval_and_keeps_valid_until_exclusive(): void
    {
        $device = Device::factory()->create();
        $historicalSensor = Sensor::factory()->create(['device_id' => $device->id]);
        $currentSensor = Sensor::factory()->create(['device_id' => $device->id]);
        $handover = CarbonImmutable::parse('2026-09-15T12:00:00Z');

        DeviceSensorMapping::create([
            'device_id' => $device->id,
            'sensor_id' => $historicalSensor->id,
            'source' => 'ingestion_service',
            'external_key' => 'temperature',
            'is_active' => false,
            'valid_from' => $handover->subHour(),
            'valid_until' => $handover,
        ]);
        DeviceSensorMapping::create([
            'device_id' => $device->id,
            'sensor_id' => $currentSensor->id,
            'source' => 'ingestion_service',
            'external_key' => 'temperature',
            'is_active' => true,
            'valid_from' => $handover,
            'valid_until' => null,
        ]);

        $service = app(SensorMappingService::class);

        $this->assertTrue($historicalSensor->is($service->findSensorByExternalKey(
            $device,
            'ingestion_service',
            'temperature',
            $handover->subSecond(),
        )));
        $this->assertTrue($currentSensor->is($service->findSensorByExternalKey(
            $device,
            'ingestion_service',
            'temperature',
            $handover,
        )));
    }
}
