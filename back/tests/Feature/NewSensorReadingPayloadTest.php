<?php

namespace Tests\Feature;

use App\Events\NewSensorReading;
use App\Models\Sensor;
use App\Models\SensorReading;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 6 Task 6.7 — exact wire-contract for the public sensor-reading broadcast payload. The
 * minimal identity/value/time triad plus the additive event envelope is present; the display
 * metadata that used to leak on every reading (sensor_name/sensor_type/unit/device_name/lab_name)
 * is gone. Guests source display metadata once from the public graph bootstrap, not per reading.
 */
class NewSensorReadingPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        $sensor = Sensor::factory()->create();
        $reading = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 42.5,
            'reading_time' => '2026-09-09 05:30:00',
        ]);

        return (new NewSensorReading($reading))->broadcastWith();
    }

    public function test_public_payload_exposes_only_identity_value_and_time_plus_envelope(): void
    {
        $payload = $this->payload();

        // Core reading keys present.
        foreach (['reading_id', 'sensor_id', 'value', 'reading_time'] as $key) {
            $this->assertArrayHasKey($key, $payload);
        }

        // Additive event envelope merged in (Stage 2.2) — reuse, not reinvented.
        foreach (['event_id', 'event_type', 'event_version', 'occurred_at', 'aggregate_type'] as $key) {
            $this->assertArrayHasKey($key, $payload);
        }
    }

    public function test_public_payload_removes_display_metadata(): void
    {
        $payload = $this->payload();

        foreach (['sensor_name', 'sensor_type', 'unit', 'device_name', 'lab_name'] as $removed) {
            $this->assertArrayNotHasKey($removed, $payload);
        }
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
