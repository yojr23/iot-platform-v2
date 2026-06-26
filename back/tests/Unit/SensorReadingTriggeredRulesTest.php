<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SensorType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensorReadingTriggeredRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_triggered_alert_rules_respect_global_device_and_sensor_scope(): void
    {
        $sensorType = SensorType::factory()->create();

        $deviceA = Device::factory()->create();
        $sensorA = Sensor::factory()->create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $deviceA->id,
        ]);

        $deviceB = Device::factory()->create();
        $sensorB = Sensor::factory()->create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $deviceB->id,
        ]);

        $globalRule = AlertRule::create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => null,
            'sensor_id' => null,
            'min_value' => null,
            'max_value' => 30,
            'severity' => 'warning',
            'message' => 'Regla global',
            'name' => 'Global',
        ]);

        $deviceRule = AlertRule::create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $deviceA->id,
            'sensor_id' => null,
            'min_value' => null,
            'max_value' => 30,
            'severity' => 'warning',
            'message' => 'Regla por dispositivo',
            'name' => 'Device',
        ]);

        $sensorRule = AlertRule::create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $deviceA->id,
            'sensor_id' => $sensorA->id,
            'min_value' => null,
            'max_value' => 30,
            'severity' => 'warning',
            'message' => 'Regla por sensor',
            'name' => 'Sensor',
        ]);

        AlertRule::create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $deviceB->id,
            'sensor_id' => null,
            'min_value' => null,
            'max_value' => 30,
            'severity' => 'warning',
            'message' => 'No aplica por dispositivo',
            'name' => 'OtherDevice',
        ]);

        AlertRule::create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $deviceA->id,
            'sensor_id' => $sensorB->id,
            'min_value' => null,
            'max_value' => 30,
            'severity' => 'warning',
            'message' => 'No aplica por sensor',
            'name' => 'OtherSensor',
        ]);

        $reading = SensorReading::factory()->create([
            'sensor_id' => $sensorA->id,
            'value' => 40,
        ]);

        $triggeredIds = $reading->triggeredAlertRules()->pluck('id')->all();

        $this->assertCount(3, $triggeredIds);
        $this->assertContains($globalRule->id, $triggeredIds);
        $this->assertContains($deviceRule->id, $triggeredIds);
        $this->assertContains($sensorRule->id, $triggeredIds);
    }

    public function test_check_for_alert_does_not_duplicate_existing_alerts(): void
    {
        $sensor = Sensor::factory()->create();

        $rule = AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => $sensor->device_id,
            'sensor_id' => $sensor->id,
            'min_value' => null,
            'max_value' => 20,
            'severity' => 'warning',
            'message' => 'Límite máximo',
            'name' => 'No Duplicates',
        ]);

        $reading = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 25,
        ]);

        $reading->checkForAlert();
        $reading->checkForAlert();

        $this->assertSame(
            1,
            Alert::query()
                ->where('sensor_reading_id', $reading->id)
                ->where('alert_rule_id', $rule->id)
                ->count()
        );
    }

    public function test_threshold_edges_are_inclusive_for_alert_trigger(): void
    {
        $sensor = Sensor::factory()->create();

        $minRule = AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => $sensor->device_id,
            'sensor_id' => $sensor->id,
            'min_value' => 10,
            'max_value' => null,
            'severity' => 'warning',
            'message' => 'Min edge',
            'name' => 'MinEdge',
        ]);

        $maxRule = AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => $sensor->device_id,
            'sensor_id' => $sensor->id,
            'min_value' => null,
            'max_value' => 20,
            'severity' => 'warning',
            'message' => 'Max edge',
            'name' => 'MaxEdge',
        ]);

        $atMin = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 10,
        ]);

        $atMax = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 20,
        ]);

        $atMin->checkForAlert();
        $atMax->checkForAlert();

        $this->assertDatabaseHas('alerts', [
            'sensor_reading_id' => $atMin->id,
            'alert_rule_id' => $minRule->id,
        ]);

        $this->assertDatabaseHas('alerts', [
            'sensor_reading_id' => $atMax->id,
            'alert_rule_id' => $maxRule->id,
        ]);
    }
}
