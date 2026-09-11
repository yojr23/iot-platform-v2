<?php

namespace Tests\Unit;

use App\Models\Alert;
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
use RuntimeException;
use Tests\TestCase;

/**
 * PLAN.md Stage 8.2 / audit.md §13: "Existing rate limiting is not an idempotency ledger;
 * acquiring a rate-limit key before a failed send can suppress a retry." These tests pin down the
 * fix: `NotificationService::notifyDangerAlertByEmail()` must release its rate-limit reservation
 * when the underlying send fails, so a legitimate subsequent attempt (a queued job retry, or the
 * next triggering alert) is not permanently suppressed for the rest of the configured window — while
 * a send that actually succeeds must still suppress duplicates within that window (no regression on
 * the throttle's actual purpose, already covered end-to-end by
 * DangerAlertEmailTest::test_danger_alert_email_is_rate_limited_for_burst_events).
 */
class NotificationServiceRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function makeDangerAlert(): Alert
    {
        // `AlertObserver::created()` (fired below by both the auto-created and any manually
        // created `Alert`) dispatches `SendDangerAlertEmailJob::dispatch()->afterCommit()`. Without
        // `Queue::fake()`, the `sync` queue used by this test run executes that job immediately —
        // stealing this test's own `Mail::shouldReceive()` expectation and pre-acquiring the very
        // rate-limit cache key these assertions are about — before the test even reaches its own
        // explicit `notifyDangerAlertByEmail()` calls (see AlertEmailAsyncDeliveryTest, which faces
        // the same job and always `Queue::fake()`s for exactly this reason).
        Queue::fake();

        $sensorType = SensorType::factory()->create();
        $device = Device::factory()->create();
        $sensor = Sensor::factory()->create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $device->id,
        ]);

        $rule = AlertRule::create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $device->id,
            'sensor_id' => $sensor->id,
            'min_value' => null,
            'max_value' => 50,
            'severity' => 'danger',
            'message' => 'Valor peligroso detectado',
            'name' => 'Danger Rule',
        ]);

        // `SensorReading::factory()->create()` fires `SensorReadingObserver`, which delegates to
        // `AlertService::createAlertsForReading()` and already creates an `Alert` for this
        // reading/rule pair (value 80 exceeds the rule's max_value 50). Stage 2's
        // `alerts.sensor_reading_id`+`alert_rule_id` unique constraint means a second, manual
        // `Alert::create()` for the same pair would collide with that auto-created row — so fetch
        // the one the real ingestion path already produced instead of duplicating it.
        $reading = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 80,
        ]);

        return Alert::where('sensor_reading_id', $reading->id)
            ->where('alert_rule_id', $rule->id)
            ->firstOrFail();
    }

    public function test_a_failed_send_releases_the_rate_limit_so_a_retry_is_not_suppressed(): void
    {
        SystemSetting::set('mail_to', 'alerts@example.test');
        SystemSetting::set('danger_email_rate_limit_seconds', 120, 'integer', 'alerts');

        $alert = $this->makeDangerAlert();
        $service = app(NotificationService::class);

        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('smtp unavailable'));

        $firstAttempt = $service->notifyDangerAlertByEmail($alert);
        $this->assertFalse($firstAttempt, 'a thrown SMTP exception must be reported as a failed send');

        // The gap this guards against: acquiring the rate-limit key up front and never releasing it
        // on failure would make this second call return false too, even though no email has ever
        // actually been sent for this alert.
        Mail::shouldReceive('send')->once()->andReturnNull();

        $secondAttempt = $service->notifyDangerAlertByEmail($alert);
        $this->assertTrue($secondAttempt, 'a retry after a failed send must not be suppressed by the rate limit');
    }

    public function test_a_successful_send_still_suppresses_a_duplicate_within_the_rate_limit_window(): void
    {
        SystemSetting::set('mail_to', 'alerts@example.test');
        SystemSetting::set('danger_email_rate_limit_seconds', 120, 'integer', 'alerts');

        $alert = $this->makeDangerAlert();
        $service = app(NotificationService::class);

        Mail::shouldReceive('send')->once()->andReturnNull();

        $this->assertTrue($service->notifyDangerAlertByEmail($alert));

        // No second `Mail::send` expectation is registered: if the fix over-releases the rate
        // limit (e.g. also releasing it on success), this second call would try to send again and
        // Mockery would fail this test for an unexpected call.
        $this->assertFalse($service->notifyDangerAlertByEmail($alert));
    }
}
