<?php

namespace Tests\Feature;

use App\Jobs\EvaluateSensorReadingAlerts;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Services\Alerts\AlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SensorReadingAlertEvaluationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_reading_dispatches_alert_evaluation_after_commit(): void
    {
        Queue::fake();

        $reading = SensorReading::factory()->create();

        Queue::assertPushed(EvaluateSensorReadingAlerts::class, function (EvaluateSensorReadingAlerts $job) use ($reading) {
            return $job->sensorReadingId === $reading->id;
        });
    }

    public function test_job_evaluates_alerts_for_the_reading(): void
    {
        Queue::fake();
        [$reading, $rule] = $this->readingThatViolatesARule();

        $this->assertDatabaseMissing('alerts', [
            'sensor_reading_id' => $reading->id,
            'alert_rule_id' => $rule->id,
        ]);

        (new EvaluateSensorReadingAlerts($reading->id))->handle();

        $this->assertDatabaseHas('alerts', [
            'sensor_reading_id' => $reading->id,
            'alert_rule_id' => $rule->id,
            'resolved' => false,
        ]);
    }

    public function test_retrying_after_a_transient_evaluation_failure_can_create_the_alert(): void
    {
        Queue::fake();
        [$reading, $rule] = $this->readingThatViolatesARule();

        $failingService = Mockery::mock(AlertService::class);
        $failingService->shouldReceive('createAlertsForReading')->once()->andThrow(new RuntimeException('temporary database failure'));
        app()->instance(AlertService::class, $failingService);

        try {
            (new EvaluateSensorReadingAlerts($reading->id))->handle();
            $this->fail('The transient evaluation failure should be released to the queue worker.');
        } catch (RuntimeException $exception) {
            $this->assertSame('temporary database failure', $exception->getMessage());
        } finally {
            app()->forgetInstance(AlertService::class);
        }

        (new EvaluateSensorReadingAlerts($reading->id))->handle();

        $this->assertDatabaseHas('alerts', [
            'sensor_reading_id' => $reading->id,
            'alert_rule_id' => $rule->id,
        ]);
    }

    public function test_duplicate_job_execution_does_not_create_duplicate_alerts(): void
    {
        Queue::fake();
        [$reading, $rule] = $this->readingThatViolatesARule();

        (new EvaluateSensorReadingAlerts($reading->id))->handle();
        (new EvaluateSensorReadingAlerts($reading->id))->handle();

        $this->assertSame(1, Alert::query()
            ->where('sensor_reading_id', $reading->id)
            ->where('alert_rule_id', $rule->id)
            ->count());
    }

    /** @return array{SensorReading, AlertRule} */
    private function readingThatViolatesARule(): array
    {
        $sensor = Sensor::factory()->create();
        $rule = AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => $sensor->device_id,
            'sensor_id' => $sensor->id,
            'min_value' => null,
            'max_value' => 20,
            'severity' => 'warning',
            'message' => 'Maximum exceeded',
            'name' => 'Retryable alert',
        ]);

        return [SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 21,
        ]), $rule];
    }
}
