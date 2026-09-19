<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BACK-01 (PLAN Mac M3): alert-rule creation is owned solely by the JSON API
 * (StoreAlertRuleRequest -> ApiAlertRuleController). The legacy Blade write path
 * was retired; these guard the same validation invariants through the canonical
 * endpoint.
 */
class AlertRuleValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_requires_at_least_one_threshold_value(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sensorType = SensorType::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/alert-rules', [
                'sensor_type_id' => $sensorType->id,
                'severity' => 'warning',
                'message' => 'Regla inválida',
                'min_value' => null,
                'max_value' => null,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['min_value']);

        $this->assertDatabaseCount('alert_rules', 0);
    }

    public function test_store_rejects_sensor_that_does_not_belong_to_selected_device(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $sensor = Sensor::factory()->create();
        $anotherDevice = Device::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/alert-rules', [
                'sensor_type_id' => $sensor->sensor_type_id,
                'device_id' => $anotherDevice->id,
                'sensor_id' => $sensor->id,
                'min_value' => 10,
                'max_value' => 20,
                'severity' => 'warning',
                'message' => 'Regla inválida',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sensor_id']);

        $this->assertDatabaseCount('alert_rules', 0);
    }

    public function test_store_rejects_mismatched_sensor_type_for_selected_sensor(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $sensor = Sensor::factory()->create();
        $otherType = SensorType::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/alert-rules', [
                'sensor_type_id' => $otherType->id,
                'sensor_id' => $sensor->id,
                'min_value' => 10,
                'max_value' => 20,
                'severity' => 'warning',
                'message' => 'Regla inválida',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sensor_type_id']);

        $this->assertDatabaseCount('alert_rules', 0);
    }
}
