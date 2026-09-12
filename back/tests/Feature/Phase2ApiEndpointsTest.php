<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
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

    /**
     * Gate 6 Task 6.1: the anonymous dashboard/public product-data endpoint was removed. Guests
     * get no operational device/sensor inventory; authenticated callers use /api/dashboard/metrics
     * (covered above) and public graphs come from /api/public/graph/bootstrap.
     */
    public function test_public_dashboard_payload_route_removed_in_gate6(): void
    {
        $this->getJson('/api/dashboard/public')->assertNotFound();
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

        // Gate 6 Task 6.1: the anonymous /devices/{device}/sensors route was removed; the single
        // authenticated owner is /devices/{device}/sensor-list (same controller action).
        $this->actingAs($user)->getJson("/api/devices/{$device->id}/sensor-list")
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
        // SEC-ALERT-001: reads stay open to any verified user; resolve/resolve-all are admin-only
        // (see AlertPolicy + tests/Feature/Api/AlertAuthorizationTest.php).
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
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

        // SEC-AUTH-002: resolve/resolve-all are now also gated by Sanctum token abilities
        // (`ability:alerts:resolve,admin`), which inspect `$request->user()->currentAccessToken()`.
        // Plain `actingAs()` never attaches a token (that's only true for real PAT/stateful-SPA
        // requests, where Sanctum's guard attaches a real token or a TransientToken respectively),
        // so these two calls use Sanctum's own `actingAs()` test helper to mint a matching mock
        // token instead of Laravel's generic one.
        Sanctum::actingAs($admin, ['*']);

        $this->patchJson("/api/alerts/{$activeAlert->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.id', $activeAlert->id)
            ->assertJsonPath('data.resolved', true);

        $this->postJson('/api/alerts/resolve-all')
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

    /**
     * Gate 6 Task 6.1: the anonymous config/public endpoint was removed. The safe-frontend-settings
     * contract now lives behind auth at /api/config/runtime (covered by the two runtime tests below).
     */
    public function test_public_config_route_removed_in_gate6(): void
    {
        $this->getJson('/api/config/public')->assertNotFound();
    }

    public function test_runtime_config_requires_authentication(): void
    {
        $this->getJson('/api/config/runtime')->assertUnauthorized();
    }

    public function test_runtime_config_exposes_authenticated_alert_runtime_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        SystemSetting::set('mail_password', 'super-secret-password', 'string', 'mail');
        SystemSetting::set('alert_sound_enabled', 1, 'boolean', 'alerts');
        SystemSetting::set('alert_threshold', 7, 'integer', 'alerts');

        $response = $this->actingAs($user)->getJson('/api/config/runtime');

        $response->assertOk()
            ->assertJsonPath('alert_sound_enabled', true)
            ->assertJsonMissingPath('alert_threshold')
            ->assertJsonMissingPath('sensor_update_interval');

        // `app_url` is an intentional runtime key, not a leak — `ConfigGeneralUpdateTest::
        // test_admin_can_update_general_config_and_it_round_trips_via_runtime()` pins down that
        // `/api/config/runtime` must reflect the general config's `app_url` after an update.
        $this->assertSame(['alert_sound_enabled', 'app_url'], array_keys($response->json()));
        $this->assertStringNotContainsString('super-secret-password', $response->getContent());
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
