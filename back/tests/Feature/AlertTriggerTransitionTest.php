<?php

namespace Tests\Feature;

use App\Events\NewAlertTriggered;
use App\Jobs\EvaluateSensorReadingAlerts;
use App\Jobs\SendDangerAlertEmailJob;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Device;
use App\Models\DomainEventOutbox;
use App\Models\Lab;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SensorType;
use App\Services\Alerts\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PLAN.md Stage 7.5 — `alert.triggered` now shares the same durable outbox path as
 * `alert.resolved` (Stage 4.2, see AlertResolveTransitionTest):
 * App\Services\Alerts\AlertService::createAlertsForReading() writes the outbox row in the same
 * transaction as Alert::create(), and App\Observers\AlertObserver::created() no longer broadcasts
 * NewAlertTriggered synchronously — App\Services\Ingestion\DomainEventBroadcastConsumer is the
 * sole dispatcher now (see DomainEventBroadcastConsumerTest for the consumer-side coverage).
 */
class AlertTriggerTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function makeThresholdRuleSensor(): Sensor
    {
        $sensorType = SensorType::factory()->create();
        $sensor = Sensor::factory()->create(['sensor_type_id' => $sensorType->id]);

        AlertRule::create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => null,
            'sensor_id' => null,
            'min_value' => null,
            'max_value' => 50,
            'severity' => 'warning',
            'message' => 'Valor alto detectado',
            'name' => 'Threshold Rule',
        ]);

        return $sensor;
    }

    public function test_creating_an_alert_records_exactly_one_alert_triggered_outbox_row(): void
    {
        $sensor = $this->makeThresholdRuleSensor();

        $reading = SensorReading::factory()->create(['sensor_id' => $sensor->id, 'value' => 80]);

        $alert = Alert::where('sensor_reading_id', $reading->id)->firstOrFail();

        $this->assertSame(1, DomainEventOutbox::query()
            ->where('event_type', 'alert.triggered')
            ->where('aggregate_type', 'alert')
            ->where('aggregate_id', (string) $alert->id)
            ->count());
    }

    public function test_evaluating_a_reading_with_a_resolved_alert_does_not_create_a_duplicate_or_outbox_event(): void
    {
        $sensor = $this->makeThresholdRuleSensor();
        $reading = SensorReading::withoutEvents(fn () => SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 80,
        ]));
        $rule = AlertRule::query()->where('sensor_type_id', $sensor->sensor_type_id)->sole();
        $alert = Alert::create([
            'sensor_reading_id' => $reading->id,
            'alert_rule_id' => $rule->id,
            'resolved' => true,
        ]);

        (new EvaluateSensorReadingAlerts($reading->id))->handle();

        $this->assertSame(1, Alert::query()
            ->where('sensor_reading_id', $reading->id)
            ->where('alert_rule_id', $alert->alert_rule_id)
            ->count());
        $this->assertSame(0, DomainEventOutbox::query()
            ->where('event_type', 'alert.triggered')
            ->count());
    }

    public function test_alert_triggered_outbox_captures_the_complete_broadcast_snapshot_at_creation(): void
    {
        $lab = Lab::factory()->create(['name' => 'Laboratorio original']);
        $device = Device::factory()->create(['lab_id' => $lab->id, 'name' => 'Dispositivo original']);
        $sensorType = SensorType::factory()->create(['name' => 'Temperatura original', 'unit' => 'C']);
        $sensor = Sensor::factory()->create([
            'device_id' => $device->id,
            'sensor_type_id' => $sensorType->id,
            'name' => 'Sensor original',
        ]);
        $rule = AlertRule::create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $device->id,
            'sensor_id' => $sensor->id,
            'min_value' => null,
            'max_value' => 50,
            'severity' => 'danger',
            'message' => 'Mensaje original',
            'name' => 'Regla original',
        ]);
        $reading = SensorReading::factory()->create(['sensor_id' => $sensor->id, 'value' => 81.5]);

        app(AlertService::class)->createAlertsForReading($reading);

        $alert = Alert::query()->where('sensor_reading_id', $reading->id)->where('alert_rule_id', $rule->id)->firstOrFail();
        $outbox = DomainEventOutbox::query()
            ->where('event_type', 'alert.triggered')
            ->where('aggregate_id', (string) $alert->id)
            ->firstOrFail();

        // JSON column key order is not guaranteed across drivers (MySQL reorders); assert the
        // payload content, not its serialized key order.
        $this->assertEqualsCanonicalizing([
            'alert_id' => $alert->id,
            'message' => 'Mensaje original',
            'severity' => 'danger',
            'value' => 81.5,
            'sensor_name' => 'Sensor original',
            'sensor_type' => 'Temperatura original',
            'unit' => 'C',
            'device_name' => 'Dispositivo original',
            'lab_name' => 'Laboratorio original',
            'timestamp' => $alert->created_at?->toIso8601String(),
        ], $outbox->payload);
    }

    public function test_alert_creation_does_not_broadcast_synchronously_but_still_queues_the_email_job(): void
    {
        Event::fake([NewAlertTriggered::class]);
        Queue::fake();

        $sensor = $this->makeThresholdRuleSensor();
        $reading = SensorReading::factory()->create(['sensor_id' => $sensor->id, 'value' => 80]);
        (new EvaluateSensorReadingAlerts($reading->id))->handle();

        // The durable-outbox path replaces the old in-request ShouldBroadcastNow call: no
        // NewAlertTriggered dispatch happens on the create() path anymore.
        Event::assertNotDispatched(NewAlertTriggered::class);

        // Email dispatch semantics (Stage 8.2, afterCommit) are unchanged by this move.
        Queue::assertPushed(SendDangerAlertEmailJob::class, 1);
    }
}
