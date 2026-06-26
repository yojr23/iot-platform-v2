<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase2ApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_public_json_status(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('app', 'iot-platform-v2')
            ->assertJsonStructure(['status', 'app', 'timestamp']);
    }

    public function test_dashboard_metrics_returns_json_summary_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Device::factory()->count(2)->create(['status' => true, 'is_active' => true]);
        Device::factory()->create(['status' => false, 'is_active' => false]);
        Sensor::factory()->count(3)->create();
        Alert::factory()->count(2)->create(['resolved' => false]);
        Alert::factory()->create(['resolved' => true, 'resolved_at' => now()]);
        $expectedTotalDevices = Device::count();
        $expectedActiveDevices = Device::where('status', true)->count();
        $expectedTotalSensors = Sensor::count();

        $this->actingAs($user)->getJson('/api/dashboard/metrics')
            ->assertOk()
            ->assertJsonPath('total_devices', $expectedTotalDevices)
            ->assertJsonPath('active_devices', $expectedActiveDevices)
            ->assertJsonPath('total_sensors', $expectedTotalSensors)
            ->assertJsonPath('active_alerts', 2)
            ->assertJsonPath('unresolved_alerts', 2)
            ->assertJsonStructure([
                'total_devices',
                'active_devices',
                'total_sensors',
                'active_alerts',
                'unresolved_alerts',
                'latest_readings',
                'system_status',
            ]);
    }

    public function test_public_dashboard_payload_supports_sensor_graphs_without_authentication(): void
    {
        $device = Device::factory()->create([
            'name' => 'Modulo Ambiental',
            'api_key' => 'device-secret-key',
            'status' => true,
            'is_active' => true,
        ]);
        $sensor = Sensor::factory()->create([
            'device_id' => $device->id,
            'name' => 'Temperatura Principal',
            'status' => true,
        ]);
        SensorReading::factory()->count(3)->create([
            'sensor_id' => $sensor->id,
            'reading_time' => now()->subMinutes(2),
        ]);

        $response = $this->getJson('/api/dashboard/public');

        $response->assertOk()
            ->assertJsonPath('total_devices', 1)
            ->assertJsonPath('active_devices', 1)
            ->assertJsonPath('total_sensors', 1)
            ->assertJsonPath('devices.0.id', $device->id)
            ->assertJsonPath('devices.0.name', 'Modulo Ambiental')
            ->assertJsonPath('devices.0.sensors.0.id', $sensor->id)
            ->assertJsonPath('devices.0.sensors.0.name', 'Temperatura Principal')
            ->assertJsonStructure([
                'total_devices',
                'active_devices',
                'total_sensors',
                'active_alerts',
                'unresolved_alerts',
                'latest_readings',
                'devices' => [
                    '*' => [
                        'id',
                        'name',
                        'status',
                        'lab',
                        'sensors' => [
                            '*' => ['id', 'name', 'unit', 'status'],
                        ],
                    ],
                ],
            ]);

        $this->assertStringNotContainsString('device-secret-key', $response->getContent());
        $this->assertArrayNotHasKey('api_key', $response->json('devices.0'));
    }

    public function test_sensor_show_and_device_sensors_return_json_without_blade(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);

        $this->actingAs($user)->getJson("/api/sensors/{$sensor->id}")
            ->assertOk()
            ->assertJsonPath('id', $sensor->id)
            ->assertJsonPath('device.id', $device->id)
            ->assertJsonStructure(['id', 'name', 'status', 'device', 'sensor_type']);

        $this->actingAs($user)->getJson("/api/devices/{$device->id}/sensors")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $sensor->id);
    }

    public function test_device_api_does_not_expose_device_api_key(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/devices/{$device->id}");

        $response->assertOk();
        $this->assertStringNotContainsString($device->api_key, $response->getContent());
    }

    public function test_alerts_index_unresolved_resolve_and_resolve_all_return_json(): void
    {
        $user = User::factory()->create();
        $activeAlert = Alert::factory()->create(['resolved' => false]);
        Alert::factory()->create(['resolved' => false]);
        Alert::factory()->create(['resolved' => true, 'resolved_at' => now()]);

        $alertsResponse = $this->actingAs($user)->getJson('/api/alerts')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->assertContains($activeAlert->id, collect($alertsResponse->json('data'))->pluck('id'));

        $this->actingAs($user)->getJson('/api/alerts/unresolved')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($user)->patchJson("/api/alerts/{$activeAlert->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.id', $activeAlert->id)
            ->assertJsonPath('data.resolved', true);

        $this->actingAs($user)->postJson('/api/alerts/resolve-all')
            ->assertOk()
            ->assertJsonPath('resolved_count', 1);
    }

    public function test_alert_rules_json_crud_uses_api_controller(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sensor = Sensor::factory()->create();

        $payload = [
            'sensor_type_id' => $sensor->sensor_type_id,
            'device_id' => $sensor->device_id,
            'sensor_id' => $sensor->id,
            'min_value' => 10,
            'max_value' => 50,
            'severity' => 'warning',
            'message' => 'Temperature outside safe range',
            'name' => 'Temperature warning',
        ];

        $created = $this->actingAs($admin)->postJson('/api/alert-rules', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Temperature warning')
            ->assertJsonPath('data.sensor.id', $sensor->id);

        $alertRuleId = $created->json('data.id');

        $this->actingAs($admin)->getJson('/api/alert-rules')
            ->assertOk()
            ->assertJsonPath('data.0.id', $alertRuleId);

        $this->actingAs($admin)->getJson("/api/alert-rules/{$alertRuleId}")
            ->assertOk()
            ->assertJsonPath('data.id', $alertRuleId);

        $this->actingAs($admin)->putJson("/api/alert-rules/{$alertRuleId}", array_merge($payload, [
            'message' => 'Updated warning',
            'name' => 'Updated rule',
        ]))->assertOk()
            ->assertJsonPath('data.name', 'Updated rule')
            ->assertJsonPath('data.message', 'Updated warning');

        $this->actingAs($admin)->deleteJson("/api/alert-rules/{$alertRuleId}")
            ->assertOk()
            ->assertJsonPath('message', 'Regla de alerta eliminada correctamente.');

        $this->assertDatabaseMissing('alert_rules', ['id' => $alertRuleId]);
    }

    public function test_alert_rule_create_legacy_api_path_returns_json_metadata_not_blade(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sensor = Sensor::factory()->create();

        $this->actingAs($admin)->getJson('/api/alert-rules/create')
            ->assertOk()
            ->assertJsonStructure(['sensor_types', 'devices', 'sensors'])
            ->assertJsonPath('sensors.0.id', $sensor->id);
    }

    public function test_public_config_exposes_only_safe_frontend_settings(): void
    {
        SystemSetting::set('mail_password', 'super-secret-password', 'string', 'mail');
        SystemSetting::set('alert_sound_enabled', 1, 'boolean', 'alerts');
        SystemSetting::set('alert_threshold', 7, 'integer', 'alerts');

        $response = $this->getJson('/api/config/public');

        $response->assertOk()
            ->assertJsonPath('alert_sound_enabled', true)
            ->assertJsonPath('alert_threshold', 7)
            ->assertJsonStructure(['app_name', 'alert_sound_enabled', 'alert_threshold', 'sensor_update_interval', 'pusher']);

        $this->assertStringNotContainsString('super-secret-password', $response->getContent());
        $this->assertArrayNotHasKey('mail_password', $response->json());
    }

    public function test_alert_config_can_be_read_and_updated_as_json(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->getJson('/api/config/alerts')
            ->assertOk()
            ->assertJsonStructure([
                'mail_enabled',
                'alert_sound_enabled',
                'alert_threshold',
                'sensor_update_interval',
                'danger_email_rate_limit_seconds',
            ]);

        $this->actingAs($admin)->putJson('/api/config/alerts', [
            'mail_enabled' => true,
            'alert_sound_enabled' => false,
            'alert_threshold' => 12,
            'sensor_update_interval' => 3000,
            'danger_email_rate_limit_seconds' => 90,
        ])->assertOk()
            ->assertJsonPath('alert_sound_enabled', false)
            ->assertJsonPath('alert_threshold', 12)
            ->assertJsonPath('danger_email_rate_limit_seconds', 90);

        $this->assertFalse(SystemSetting::get('alert_sound_enabled'));
        $this->assertSame(12, SystemSetting::get('alert_threshold'));
    }

    public function test_email_config_json_does_not_expose_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        SystemSetting::set('mail_password', 'super-secret-password', 'string', 'mail');

        $response = $this->actingAs($admin)->getJson('/api/config/email');

        $response->assertOk()
            ->assertJsonPath('password_configured', true)
            ->assertJsonStructure([
                'mail_mailer',
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_encryption',
                'mail_from_address',
                'mail_from_name',
                'mail_to',
                'password_configured',
            ]);

        $this->assertStringNotContainsString('super-secret-password', $response->getContent());
        $this->assertArrayNotHasKey('mail_password', $response->json());
    }

    public function test_email_config_update_preserves_existing_password_when_not_provided(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        SystemSetting::set('mail_password', 'existing-secret', 'string', 'mail');

        $this->actingAs($admin)->putJson('/api/config/email', [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.test',
            'mail_port' => 587,
            'mail_username' => 'alerts@example.test',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'alerts@example.test',
            'mail_from_name' => 'SINOA',
            'mail_to' => 'operator@example.test',
        ])->assertOk()
            ->assertJsonPath('password_configured', true);

        $this->assertSame('existing-secret', SystemSetting::get('mail_password'));
    }

    public function test_email_config_test_endpoint_accepts_request_without_real_smtp(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        SystemSetting::set('mail_password', 'existing-secret', 'string', 'mail');

        $this->actingAs($admin)->postJson('/api/config/email/test', [
            'test_email' => 'operator@example.test',
        ])->assertOk()
            ->assertJsonPath('message', 'Email de prueba enviado correctamente.');
    }
}
