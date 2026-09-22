<?php

namespace Tests\Feature\Observability;

use App\Models\Device;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use RuntimeException;

/**
 * AUTHORED — MAC EXECUTION PENDING.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private const SMTP_PASSWORD_SENTINEL = 'AUDIT_SMTP_PASSWORD_SENTINEL';

    /** @var array<int, MessageLogged> */
    private array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->captured = [];
        Log::listen(function (MessageLogged $event): void {
            $this->captured[] = $event;
        });
    }

    public function test_general_configuration_update_records_changed_keys_without_values(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->putJson('/api/config/general', [
            'app_name' => 'SINOA Audit Lab',
            'app_url' => 'https://audit.example.test',
        ], ['X-Request-Id' => 'UNTRUSTED_AUDIT_REQUEST_ID'])->assertOk();
        $effectiveRequestId = (string) $response->headers->get('X-Request-Id');

        $this->assertNotSame('UNTRUSTED_AUDIT_REQUEST_ID', $effectiveRequestId);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'system_setting.changed',
            'resource_type' => 'system_setting',
            'metadata' => json_encode(['setting_key' => 'app_name', 'value_changed' => true]),
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'system_setting.changed',
            'resource_type' => 'system_setting',
            'metadata' => json_encode(['setting_key' => 'app_url', 'value_changed' => true]),
        ]);

        $audit = \DB::table('audit_logs')
            ->where('action', 'system_setting.changed')
            ->where('actor_user_id', $admin->id)
            ->whereJsonContains('metadata->setting_key', 'app_name')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($effectiveRequestId, $audit->request_id);
        $this->assertStringNotContainsString('SINOA Audit Lab', (string) $audit->metadata);
        $this->assertStringNotContainsString('https://audit.example.test', (string) $audit->metadata);

        $projection = $this->auditProjectionFor('system_setting.changed', 'app_name');
        $this->assertNotNull($projection);
        $this->assertSame($admin->id, $projection->context['actor_user_id']);
        $this->assertSame('system_setting', $projection->context['resource_type']);
        $this->assertSame($effectiveRequestId, $projection->context['request_id']);
        $this->assertSame(['setting_key' => 'app_name', 'value_changed' => true], $projection->context['metadata']);
        $this->assertArrayNotHasKey('ip_address', $projection->context);
    }

    public function test_email_configuration_audit_never_records_or_logs_the_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->putJson('/api/config/email', [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.gmail.com',
            'mail_port' => 587,
            'mail_username' => 'audit@example.test',
            'mail_password' => self::SMTP_PASSWORD_SENTINEL,
            'mail_encryption' => 'tls',
            'mail_from_address' => 'noreply@example.test',
            'mail_from_name' => 'SINOA',
            'mail_to' => 'alerts@example.test',
        ])->assertOk();

        $audit = \DB::table('audit_logs')
            ->where('action', 'system_setting.changed')
            ->where('actor_user_id', $admin->id)
            ->whereJsonContains('metadata->setting_key', 'mail_password')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringNotContainsString(self::SMTP_PASSWORD_SENTINEL, (string) $audit->metadata);

        foreach ($this->captured as $event) {
            $encoded = json_encode([$event->message, $event->context]);
            $this->assertStringNotContainsString(self::SMTP_PASSWORD_SENTINEL, $encoded ?: '');
        }

        $projection = $this->auditProjectionFor('system_setting.changed', 'mail_password');
        $this->assertNotNull($projection);
        $this->assertSame(['setting_key' => 'mail_password', 'value_changed' => true], $projection->context['metadata']);
        $this->assertArrayNotHasKey('ip_address', $projection->context);
    }

    public function test_device_key_rotation_records_the_actor_and_device_without_key_material(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $device = Device::factory()->create();

        $response = $this->actingAs($admin)->postJson("/api/devices/{$device->id}/rotate-key")
            ->assertOk();
        $rotatedKey = (string) $response->json('api_key');

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'device.api_key.rotated',
            'resource_type' => 'device',
            'resource_id' => $device->id,
        ]);

        $projection = $this->auditProjectionFor('device.api_key.rotated');
        $this->assertNotNull($projection);
        $this->assertSame($admin->id, $projection->context['actor_user_id']);
        $this->assertSame($device->id, $projection->context['resource_id']);
        $this->assertArrayNotHasKey('api_key', $projection->context);

        foreach ($this->captured as $event) {
            $encoded = json_encode([$event->message, $event->context]);
            $this->assertStringNotContainsString($rotatedKey, $encoded ?: '');
        }
    }

    public function test_system_setting_audit_allowlist_rejects_hostile_metadata_spellings(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sentinels = [
            'apiKey' => 'AUDIT_API_KEY_SENTINEL',
            'oldSecret' => 'AUDIT_OLD_SECRET_SENTINEL',
            'newSecret' => 'AUDIT_NEW_SECRET_SENTINEL',
            'new' => 'AUDIT_NEW_VALUE_SENTINEL',
            'settingValue' => 'AUDIT_CAMEL_SETTING_VALUE_SENTINEL',
            'arbitrary' => 'AUDIT_ARBITRARY_VALUE_SENTINEL',
        ];

        app(AuditService::class)->log(
            'system_setting.changed',
            'system_setting',
            991,
            $admin->id,
            null,
            ['setting_key' => 'mail_password', 'value_changed' => true] + $sentinels,
        );

        $audit = \DB::table('audit_logs')->where('resource_id', 991)->first();

        $this->assertNotNull($audit);
        $this->assertSame(
            json_encode(['setting_key' => 'mail_password', 'value_changed' => true]),
            $audit->metadata,
        );

        $projection = $this->auditProjectionFor('system_setting.changed', 'mail_password');
        $this->assertNotNull($projection);
        $this->assertSame(['setting_key' => 'mail_password', 'value_changed' => true], $projection->context['metadata']);

        foreach ($sentinels as $sentinel) {
            $this->assertStringNotContainsString($sentinel, (string) $audit->metadata);
            $this->assertStringNotContainsString($sentinel, json_encode($projection->context) ?: '');
        }
    }

    public function test_unknown_audit_events_drop_metadata_entirely(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        app(AuditService::class)->log(
            'unknown.audit.event',
            'unknown_resource',
            992,
            $admin->id,
            null,
            ['arbitrary' => 'AUDIT_UNKNOWN_METADATA_SENTINEL'],
        );

        $audit = \DB::table('audit_logs')->where('resource_id', 992)->first();

        $this->assertNotNull($audit);
        $this->assertNull($audit->metadata);

        $projection = $this->auditProjectionFor('unknown.audit.event');
        $this->assertNotNull($projection);
        $this->assertNull($projection->context['metadata']);
    }

    public function test_successful_role_change_preserves_only_supported_role_codes_in_audit_metadata(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $superadmin = User::factory()->create([
            'is_admin' => true,
            'role_id' => Role::where('code', 'superadmin')->value('id'),
        ]);
        $target = User::factory()->create([
            'is_admin' => false,
            'role_id' => Role::where('code', 'user')->value('id'),
        ]);

        $this->actingAs($superadmin)->patchJson("/api/users/{$target->id}/role", [
            'role_code' => 'admin',
        ])->assertOk();

        $audit = \DB::table('audit_logs')
            ->where('action', 'user.role.changed')
            ->where('actor_user_id', $superadmin->id)
            ->where('resource_id', $target->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(json_encode(['old_role' => 'user', 'new_role' => 'admin']), $audit->metadata);

        $projection = $this->auditProjectionFor('user.role.changed');
        $this->assertNotNull($projection);
        $this->assertSame(['old_role' => 'user', 'new_role' => 'admin'], $projection->context['metadata']);
    }

    public function test_role_change_metadata_rejects_unknown_codes_and_extra_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        app(AuditService::class)->log(
            'user.role.changed',
            'user',
            993,
            $admin->id,
            null,
            [
                'old_role' => 'user',
                'new_role' => 'not-a-supported-role',
                'apiKey' => 'AUDIT_ROLE_METADATA_SENTINEL',
            ],
        );

        $audit = \DB::table('audit_logs')->where('resource_id', 993)->first();

        $this->assertNotNull($audit);
        $this->assertNull($audit->metadata);

        $projection = $this->auditProjectionFor('user.role.changed');
        $this->assertNotNull($projection);
        $this->assertNull($projection->context['metadata']);
    }

    public function test_rotate_key_rolls_back_when_durable_audit_insert_fails_then_recovers(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $device = Device::factory()->create();
        $previousKey = (string) $device->pullPlaintextApiKey();

        $this->assertNotSame('', $previousKey);
        $this->assertTrue($device->authenticate($previousKey));

        $switchableAudit = new class extends AuditService
        {
            public bool $shouldFail = true;

            public function logDeviceApiKeyRotated(int $deviceId, ?Request $request = null): void
            {
                if ($this->shouldFail) {
                    throw new RuntimeException('AUDIT_INSERT_FAILURE');
                }

                parent::logDeviceApiKeyRotated($deviceId, $request);
            }
        };
        $this->app->instance(AuditService::class, $switchableAudit);

        $this->actingAs($admin)->postJson("/api/devices/{$device->id}/rotate-key")
            ->assertStatus(500);

        $this->assertTrue($device->fresh()->authenticate($previousKey));
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'device.api_key.rotated',
            'resource_id' => $device->id,
        ]);

        // Laravel may retain the route controller (and therefore its readonly dependency) between
        // requests. Recover through the same injected double rather than assuming rebinding wins.
        $switchableAudit->shouldFail = false;

        $recovery = $this->actingAs($admin)->postJson("/api/devices/{$device->id}/rotate-key")
            ->assertOk();

        $this->assertNotSame($previousKey, (string) $recovery->json('api_key'));
        $this->assertFalse($device->fresh()->authenticate($previousKey));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'device.api_key.rotated',
            'resource_id' => $device->id,
        ]);
    }

    public function test_structured_audit_projection_failure_is_nonfatal_after_durable_insert(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Log::shouldReceive('channel')
            ->once()
            ->with('audit')
            ->andThrow(new RuntimeException('AUDIT_PROJECTION_FAILURE'));

        app(AuditService::class)->logSystemSettingChanged('app_name', null, 'changed', null);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'system_setting.changed',
            'metadata' => json_encode(['setting_key' => 'app_name', 'value_changed' => true]),
        ]);
    }

    /**
     * AUTHORED — MAC EXECUTION PENDING.
     *
     * @return MessageLogged|null
     */
    private function auditProjectionFor(string $action, ?string $settingKey = null): ?MessageLogged
    {
        foreach ($this->captured as $event) {
            if ($event->message !== 'audit_event' || ($event->context['action'] ?? null) !== $action) {
                continue;
            }

            if ($settingKey === null || ($event->context['metadata']['setting_key'] ?? null) === $settingKey) {
                return $event;
            }
        }

        return null;
    }
}
