<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BACK-01 (PLAN Mac M3): alert-rule creation is owned solely by the JSON API.
 * The legacy Blade write path was retired; this guards that a named rule is
 * persisted through the canonical endpoint.
 */
class AlertRuleNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_alert_rule_with_name(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $sensorType = SensorType::factory()->create();
        $device = Device::factory()->create();
        $sensor = Sensor::factory()->create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $device->id,
        ]);

        $payload = [
            'sensor_type_id' => $sensorType->id,
            'device_id' => $device->id,
            'sensor_id' => $sensor->id,
            'min_value' => 17,
            'max_value' => 29,
            'severity' => 'warning',
            'message' => 'Temperatura fuera de rango',
            'name' => 'Temperatura normal',
        ];

        $this->actingAs($admin)->postJson('/api/alert-rules', $payload)
            ->assertStatus(201);

        $this->assertDatabaseHas('alert_rules', [
            'sensor_id' => $sensor->id,
            'name' => 'Temperatura normal',
        ]);
    }
}
