<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Models\AlertRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

class AlertRuleNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_alert_rule_with_name(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

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

        $response = $this->actingAs($admin)->post(route('alert-rules.store'), $payload);

        $response->assertRedirect();

        $this->assertDatabaseHas('alert_rules', [
            'sensor_id' => $sensor->id,
            'name' => 'Temperatura normal',
        ]);
    }
}
