<?php

namespace Tests\Feature;

use App\Events\NewSensorReading;
use App\Models\Device;
use App\Models\Lab;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SensorType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PENDING CI EXECUTION — written on Windows, executed by GitHub Actions.
 *
 * Task 7(b) supersedes Gate 6 Task 6.7's minimization for this event: `broadcastWith()` is
 * self-contained again — full reading identity/value/time plus the denormalized sensor/device/unit
 * fields `front/src/realtime/useSensorRealtime.js`'s normalizeReading() reads — so browser delivery
 * never depends on a follow-up read of the SensorReading row. This is safe against Gate 6's
 * original info-disclosure concern because the audience decision (public vs. private channel)
 * already happened before this event is constructed
 * (`DomainEventBroadcastConsumer::broadcastSensorReadingCreated()` -> `PublicGraphVisibility`): a
 * restricted sensor's fact never reaches the public channel at all, so a fact that IS public is,
 * by construction, for a sensor already enumerated with this same metadata in the public graph
 * catalog/bootstrap.
 */
class NewSensorReadingPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $lab = Lab::factory()->create(['name' => 'Laboratorio Uno']);
        $device = Device::factory()->create(['lab_id' => $lab->id, 'name' => 'Dispositivo Uno']);
        $sensorType = SensorType::factory()->create(['name' => 'Temperatura', 'unit' => 'C']);
        $sensor = Sensor::factory()->create([
            'device_id' => $device->id,
            'sensor_type_id' => $sensorType->id,
            'name' => 'Sensor Uno',
        ]);
        $reading = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 42.5,
            'reading_time' => '2026-09-09 05:30:00',
        ]);
        $reading->load('sensor.sensorType', 'sensor.device.lab');

        return (new NewSensorReading($reading))->broadcastWith();
    }

    public function test_payload_exposes_identity_value_time_and_envelope(): void
    {
        $payload = $this->payload();

        foreach (['id', 'reading_id', 'sensor_id', 'value', 'reading_time'] as $key) {
            $this->assertArrayHasKey($key, $payload);
        }

        // Additive event envelope merged in (Stage 2.2) — reuse, not reinvented.
        foreach (['event_id', 'event_type', 'event_version', 'occurred_at', 'aggregate_type'] as $key) {
            $this->assertArrayHasKey($key, $payload);
        }
    }

    public function test_payload_is_self_contained_with_denormalized_display_metadata(): void
    {
        $payload = $this->payload();

        // Task 7(b): the exact fields front/src/realtime/useSensorRealtime.js's normalizeReading()
        // reads off a live event, so a browser never needs a separate read of the sensor/device row.
        $this->assertSame('Sensor Uno', $payload['sensor_name']);
        $this->assertSame('Temperatura', $payload['sensor_type']);
        $this->assertSame('C', $payload['unit']);
        $this->assertSame('Dispositivo Uno', $payload['device_name']);
        $this->assertSame('Laboratorio Uno', $payload['lab_name']);
    }

    public function test_value_is_float_and_reading_time_is_utc_iso_zulu(): void
    {
        $payload = $this->payload();

        $this->assertIsFloat($payload['value']);
        $this->assertSame(42.5, $payload['value']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $payload['reading_time']
        );
    }
}
