<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecuritySqlInjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_rule_store_rejects_sql_injection_like_payloads(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sensorType = SensorType::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('alert-rules.create'))
            ->post(route('alert-rules.store'), [
                'sensor_type_id' => "{$sensorType->id} OR 1=1",
                'device_id' => "1; DROP TABLE users; --",
                'sensor_id' => "1 UNION SELECT * FROM users",
                'min_value' => 10,
                'max_value' => 20,
                'severity' => 'warning',
                'message' => 'SQLi attempt',
            ]);

        $response->assertRedirect(route('alert-rules.create'));
        $response->assertSessionHasErrors(['sensor_type_id', 'device_id', 'sensor_id']);

        $this->assertDatabaseCount('alert_rules', 0);
    }

    public function test_device_api_casts_per_page_and_does_not_expand_result_set_with_sqli_input(): void
    {
        Device::factory()->count(5)->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/devices?per_page=1%20OR%201=1');

        $response->assertOk()
            ->assertJsonPath('per_page', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total', 5);
    }

    public function test_sensor_store_reading_rejects_sqli_api_key_attempt(): void
    {
        config(['app.api_key' => 'valid-key']);

        $sensor = Sensor::factory()->create([
            'device_id' => \App\Models\Device::factory()->create([
                'is_active' => true,
                'status' => true,
            ])->id,
        ]);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 12.3,
            'api_key' => "valid-key' OR '1'='1",
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Unauthorized');

        $this->assertDatabaseCount('sensor_readings', 0);
    }
}
