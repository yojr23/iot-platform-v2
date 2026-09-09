<?php

namespace Tests\Feature;

use App\Jobs\SendDangerAlertEmailJob;
use App\Mail\DangerAlertMail;
use App\Models\AlertRule;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SensorType;
use App\Models\SystemSetting;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PLAN.md Stage 8.2 (audit.md §13): `NotificationService::notifyDangerAlertByEmail()` /
 * `Alert::sendDangerAlertEmail()` used to run inline inside the reading -> observer -> alert
 * request path via `AlertObserver::created()`. That path now only dispatches
 * `App\Jobs\SendDangerAlertEmailJob` (`afterCommit()`) — these tests assert the *transport* moved
 * off the request/response cycle. Severity gate, rate limiting and payload mapping stay owned by
 * `NotificationService` (unchanged) and are covered by AlertEmailTest / DangerAlertEmailTest /
 * NotificationServiceRateLimitTest — not re-tested here.
 */
class AlertEmailAsyncDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeDangerRuleSensor(): Sensor
    {
        $sensorType = SensorType::factory()->create();
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $sensor = Sensor::factory()->create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $device->id,
        ]);

        AlertRule::create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $device->id,
            'sensor_id' => $sensor->id,
            'min_value' => null,
            'max_value' => 50,
            'severity' => 'danger',
            'message' => 'Valor peligroso detectado',
            'name' => 'Danger Rule',
        ]);

        return $sensor;
    }

    public function test_legacy_ingestion_endpoint_never_blocks_on_smtp_and_only_queues_the_email(): void
    {
        Mail::fake();
        Queue::fake();
        SystemSetting::set('mail_to', 'alerts@example.test');

        $sensor = $this->makeDangerRuleSensor();
        config(['app.api_key' => 'valid-key']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 80,
            'api_key' => 'valid-key',
        ]);

        $response->assertCreated();

        // The reading and the resulting alert persisted, and the response returned, without the
        // request ever touching the mail transport (an unavailable/slow SMTP destination cannot
        // delay this response because Mail::send is only ever reached from inside the queued job).
        $this->assertDatabaseCount('sensor_readings', 1);
        $this->assertDatabaseCount('alerts', 1);
        Mail::assertNothingSent();

        Queue::assertPushed(SendDangerAlertEmailJob::class, 1);
    }

    public function test_alert_observer_queues_exactly_one_email_job_per_alert_not_a_double_send(): void
    {
        Mail::fake();
        Queue::fake();
        SystemSetting::set('mail_to', 'alerts@example.test');

        $sensor = $this->makeDangerRuleSensor();

        SensorReading::factory()->create(['sensor_id' => $sensor->id, 'value' => 80]);

        // Exactly one job per created alert: no duplicate dispatch from observer + any other path
        // (there is no competing alert.triggered domain-outbox/email consumer today — see the job's
        // class docblock).
        Queue::assertPushed(SendDangerAlertEmailJob::class, 1);
        Mail::assertNothingSent();
    }

    public function test_queued_job_still_performs_the_real_send_once_processed(): void
    {
        Mail::fake();
        Queue::fake();
        SystemSetting::set('mail_to', 'alerts@example.test');

        $sensor = $this->makeDangerRuleSensor();
        SensorReading::factory()->create(['sensor_id' => $sensor->id, 'value' => 80]);

        $dispatched = null;
        Queue::assertPushed(SendDangerAlertEmailJob::class, function (SendDangerAlertEmailJob $job) use (&$dispatched) {
            $dispatched = $job;

            return true;
        });

        $dispatched->handle(app(NotificationService::class));

        Mail::assertSent(DangerAlertMail::class, 1);
    }
}
