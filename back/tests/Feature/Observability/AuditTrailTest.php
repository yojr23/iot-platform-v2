<?php

namespace Tests\Feature\Observability;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

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

        $this->actingAs($admin)->putJson('/api/config/general', [
            'app_name' => 'SINOA Audit Lab',
            'app_url' => 'https://audit.example.test',
        ])->assertOk();

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
    }

    public function test_device_key_rotation_records_the_actor_and_device_without_key_material(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $device = Device::factory()->create();

        $this->actingAs($admin)->postJson("/api/devices/{$device->id}/rotate-key")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'device.api_key.rotated',
            'resource_type' => 'device',
            'resource_id' => $device->id,
        ]);
    }
}
