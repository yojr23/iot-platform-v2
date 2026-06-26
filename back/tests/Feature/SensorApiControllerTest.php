<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SensorApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_reading_returns_unauthorized_when_api_key_is_invalid(): void
    {
        $device = Device::factory()->create([
            'status' => true,
            'is_active' => true,
        ]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);
        $this->assertTrue((bool) $sensor->device()->first()->is_active);

        config(['app.api_key' => 'valid-key']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 21.5,
            'api_key' => 'invalid-key',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Unauthorized');

        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_store_reading_returns_forbidden_when_device_is_inactive(): void
    {
        $device = Device::factory()->create([
            'status' => false,
            'is_active' => false,
        ]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);
        $this->assertFalse((bool) $sensor->device()->first()->is_active);

        config(['app.api_key' => 'valid-key']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 25.3,
            'api_key' => 'valid-key',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error', 'Device Inactive');

        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_store_reading_returns_forbidden_when_device_status_is_false_even_if_is_active_true(): void
    {
        $device = Device::factory()->create([
            'status' => false,
            'is_active' => true,
        ]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);

        config(['app.api_key' => 'valid-key']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 15.6,
            'api_key' => 'valid-key',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error', 'Device Inactive');

        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_store_reading_creates_record_when_payload_is_valid(): void
    {
        $device = Device::factory()->create([
            'status' => true,
            'is_active' => true,
        ]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);
        $this->assertTrue((bool) $sensor->device()->first()->is_active);

        config(['app.api_key' => 'valid-key']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 42.75,
            'reading_time' => '2026-01-15 12:30:00',
            'api_key' => 'valid-key',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Reading saved successfully');

        $reading = SensorReading::query()->where('sensor_id', $sensor->id)->first();

        $this->assertNotNull($reading);
        $this->assertEqualsWithDelta(42.75, (float) $reading->value, 0.0001);
        $this->assertSame('2026-01-15 12:30:00', $reading->reading_time->format('Y-m-d H:i:s'));
    }

    public function test_store_reading_returns_unauthorized_when_configured_api_key_is_empty(): void
    {
        $device = Device::factory()->create([
            'status' => true,
            'is_active' => true,
        ]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);

        config(['app.api_key' => '']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 42.75,
            'api_key' => 'any-key-1',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Unauthorized');

        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_latest_readings_applies_limit_and_excludes_future_records(): void
    {
        Carbon::setTestNow('2026-04-26 10:00:00');

        try {
            $sensor = Sensor::factory()->create();

            $older = SensorReading::factory()->create([
                'sensor_id' => $sensor->id,
                'value' => 10,
                'reading_time' => Carbon::now()->subMinutes(20),
            ]);

            $newer = SensorReading::factory()->create([
                'sensor_id' => $sensor->id,
                'value' => 20,
                'reading_time' => Carbon::now()->subMinutes(5),
            ]);

            SensorReading::factory()->create([
                'sensor_id' => $sensor->id,
                'value' => 99,
                'reading_time' => Carbon::now()->addHour(),
            ]);

            $user = User::factory()->create();

            $response = $this->actingAs($user)->getJson("/api/sensors/{$sensor->id}/latest-readings?limit=2");

            $response->assertOk()
                ->assertJsonCount(2)
                ->assertJsonPath('0.id', $newer->id)
                ->assertJsonPath('1.id', $older->id);
        } finally {
            Carbon::setTestNow();
        }
    }
}
