<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SensorType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN.md Stage 2.5 / docs/implementation/adr-g1.md — DB backstop for the
 * App\Services\Alerts\AlertService::createAlertsForReading() check-then-create race
 * (G0D row B1). AlertService logic itself is intentionally untouched in this stage.
 */
class AlertUniqueConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_sensor_reading_and_alert_rule_pair_is_rejected_by_the_database(): void
    {
        $sensorType = SensorType::factory()->create();
        $sensor = Sensor::factory()->create(['sensor_type_id' => $sensorType->id]);
        $reading = SensorReading::factory()->create(['sensor_id' => $sensor->id]);
        $alertRule = AlertRule::factory()->create(['sensor_type_id' => $sensorType->id]);

        Alert::create([
            'sensor_reading_id' => $reading->id,
            'alert_rule_id' => $alertRule->id,
            'resolved' => false,
        ]);

        $this->expectException(QueryException::class);

        Alert::create([
            'sensor_reading_id' => $reading->id,
            'alert_rule_id' => $alertRule->id,
            'resolved' => false,
        ]);
    }
}
