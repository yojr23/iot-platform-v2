<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D4 (front_rebuild_plan/MAIN_PARITY_GAPS_PLAN.md): GET /api/devices/{device}/sensor-list
 * (DeviceApiController::sensors) now eager-loads Sensor::latestReading() (a "has one of many"
 * relation added to Sensor.php) so SensorResource's `latest_reading` carries the newest
 * {value, reading_time} per sensor without N+1 or the eager-load limit(1) grouping bug.
 * GAP: could not run `php artisan test --filter=DeviceSensorListLatestReadingTest` in this
 * session (no php/composer available) — run it on an operator machine before merging.
 */
class DeviceSensorListLatestReadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_sensor_list_includes_latest_reading_per_sensor(): void
    {
        $device = Device::factory()->create();
        $sensorA = Sensor::factory()->create(['device_id' => $device->id, 'name' => 'A Sensor']);
        $sensorB = Sensor::factory()->create(['device_id' => $device->id, 'name' => 'B Sensor']);

        SensorReading::factory()->create([
            'sensor_id' => $sensorA->id,
            'value' => 10.5,
            'reading_time' => now()->subMinutes(30),
        ]);
        $newestA = SensorReading::factory()->create([
            'sensor_id' => $sensorA->id,
            'value' => 22.5,
            'reading_time' => now()->subMinutes(1),
        ]);

        SensorReading::factory()->create([
            'sensor_id' => $sensorB->id,
            'value' => 99.9,
            'reading_time' => now()->subMinutes(45),
        ]);
        $newestB = SensorReading::factory()->create([
            'sensor_id' => $sensorB->id,
            'value' => 5.25,
            'reading_time' => now()->subMinutes(2),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/devices/{$device->id}/sensor-list");

        $response->assertOk();

        $payload = collect($response->json())->keyBy('id');

        $this->assertEquals(
            (float) $newestA->value,
            (float) $payload[$sensorA->id]['latest_reading']['value']
        );
        $this->assertEquals(
            (float) $newestB->value,
            (float) $payload[$sensorB->id]['latest_reading']['value']
        );
        $this->assertSame($sensorA->sensorType?->unit, $payload[$sensorA->id]['unit']);
    }

    public function test_device_sensor_list_latest_reading_is_null_when_sensor_has_no_readings(): void
    {
        $device = Device::factory()->create();
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/devices/{$device->id}/sensor-list");

        $response->assertOk();

        $payload = collect($response->json())->keyBy('id');

        $this->assertNull($payload[$sensor->id]['latest_reading']);
    }
}
