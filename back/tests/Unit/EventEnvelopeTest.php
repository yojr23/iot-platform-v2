<?php

namespace Tests\Unit;

use App\Events\DeviceStatusUpdated;
use App\Events\NewAlertTriggered;
use App\Events\NewSensorReading;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SensorType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN.md Stage 2.2 — canonical versioned envelope, applied to the three existing broadcast
 * events without changing their channel names or existing (legacy) broadcastWith() keys.
 */
class EventEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_sensor_reading_broadcast_payload_keeps_legacy_fields_and_adds_envelope(): void
    {
        $sensorType = SensorType::factory()->create();
        $sensor = Sensor::factory()->create(['sensor_type_id' => $sensorType->id]);
        $reading = SensorReading::factory()->create(['sensor_id' => $sensor->id]);

        $event = new NewSensorReading($reading);
        $data = $event->broadcastWith();

        // Backward-compat: existing keys untouched.
        $this->assertSame($reading->id, $data['reading_id']);
        $this->assertSame($sensor->id, $data['sensor_id']);

        // Canonical envelope, additive.
        $this->assertSame('sensor.reading.created', $data['event_type']);
        $this->assertSame(1, $data['event_version']);
        $this->assertSame('sensor_reading', $data['aggregate_type']);
        $this->assertSame($reading->id, $data['aggregate_id']);
        $this->assertSame(1, $data['aggregate_version']);
        $this->assertNotEmpty($data['event_id']);
        $this->assertNotEmpty($data['occurred_at']);
        $this->assertNotEmpty($data['correlation_id']);
        $this->assertNull($data['causation_id']);

        // Channel name stays the backward-compat public name.
        $this->assertSame('sensor.'.$sensor->id, $event->broadcastOn()->name);

        // event_id/correlation_id are stable across repeated calls on the same instance.
        $this->assertSame($data['event_id'], $event->broadcastWith()['event_id']);
    }

    public function test_new_alert_triggered_broadcast_payload_keeps_legacy_fields_and_adds_envelope(): void
    {
        $sensorType = SensorType::factory()->create();
        $sensor = Sensor::factory()->create(['sensor_type_id' => $sensorType->id]);
        $reading = SensorReading::factory()->create(['sensor_id' => $sensor->id]);
        $alertRule = AlertRule::factory()->create(['sensor_type_id' => $sensorType->id]);
        $alert = Alert::factory()->create([
            'sensor_reading_id' => $reading->id,
            'alert_rule_id' => $alertRule->id,
        ]);

        $event = new NewAlertTriggered($alert, correlationId: 'corr-123', causationId: 'cause-456');
        $data = $event->broadcastWith();

        $this->assertSame($alert->id, $data['id']);
        $this->assertSame('alert.triggered', $data['event_type']);
        $this->assertSame('alert', $data['aggregate_type']);
        $this->assertSame($alert->id, $data['aggregate_id']);
        $this->assertSame('corr-123', $data['correlation_id']);
        $this->assertSame('cause-456', $data['causation_id']);
        $this->assertSame('alerts', $event->broadcastOn()->name);
    }

    public function test_device_status_updated_broadcast_payload_keeps_legacy_fields_and_adds_envelope(): void
    {
        $device = Device::factory()->create();

        $event = new DeviceStatusUpdated($device);
        $data = $event->broadcastWith();

        $this->assertSame($device->id, $data['device_id']);
        $this->assertSame('device.status.changed', $data['event_type']);
        $this->assertSame('device', $data['aggregate_type']);
        $this->assertSame($device->id, $data['aggregate_id']);
        $this->assertSame('device-status', $event->broadcastOn()->name);
    }

    public function test_envelope_helper_nests_payload_and_keeps_metadata(): void
    {
        $device = Device::factory()->create();
        $event = new DeviceStatusUpdated($device);

        $payload = ['foo' => 'bar'];
        $envelope = $event->envelope($payload);

        $this->assertSame($payload, $envelope['payload']);
        $this->assertSame('device.status.changed', $envelope['event_type']);
        $this->assertArrayNotHasKey('payload', $event->envelopeMetadata());
    }
}
