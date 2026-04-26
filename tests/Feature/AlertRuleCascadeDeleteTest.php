<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\Sensor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertRuleCascadeDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_rule_is_deleted_when_related_sensor_is_deleted(): void
    {
        $sensor = Sensor::factory()->create();

        $rule = AlertRule::create([
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => $sensor->device_id,
            'sensor_id' => $sensor->id,
            'min_value' => null,
            'max_value' => 55,
            'severity' => 'warning',
            'message' => 'Regla asociada al sensor',
            'name' => 'Cascade Rule',
        ]);

        $this->assertDatabaseHas('alert_rules', ['id' => $rule->id]);

        $sensor->delete();

        $this->assertDatabaseMissing('alert_rules', ['id' => $rule->id]);
    }
}
