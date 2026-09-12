<?php

namespace Tests\Feature\Api;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Security\SecretSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailSecretStorageTest extends TestCase
{
    use RefreshDatabase;

    private const SENTINEL = 'SUPER_SECRET_SMTP_SENTINEL';

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.gmail.com',
            'mail_port' => 587,
            'mail_username' => 'sinoa@example.test',
            'mail_password' => self::SENTINEL,
            'mail_encryption' => 'tls',
            'mail_from_address' => 'noreply@example.test',
            'mail_from_name' => 'SINOA',
            'mail_to' => 'alerts@example.test',
        ], $overrides);
    }

    public function test_updating_email_config_stores_password_encrypted_at_rest(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->putJson('/api/config/email', $this->validPayload())->assertOk();

        $row = SystemSetting::query()->where('key', 'mail_password')->firstOrFail();

        $this->assertNotSame(self::SENTINEL, $row->value);
        $this->assertSame(self::SENTINEL, Crypt::decryptString($row->value));
    }

    public function test_show_endpoint_never_leaks_the_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->putJson('/api/config/email', $this->validPayload())->assertOk();

        $response = $this->actingAs($admin)->getJson('/api/config/email')->assertOk();

        $response->assertJsonPath('password_configured', true);
        $this->assertStringNotContainsString(self::SENTINEL, $response->getContent());
    }

    public function test_runtime_mail_config_receives_decrypted_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->putJson('/api/config/email', $this->validPayload())->assertOk();

        Mail::fake();

        $this->actingAs($admin)
            ->postJson('/api/config/email/test', ['test_email' => 'target@example.test'])
            ->assertOk();

        $this->assertSame(self::SENTINEL, config('mail.mailers.smtp.password'));
    }

    public function test_migration_encryption_is_idempotent(): void
    {
        SystemSetting::set('mail_password', self::SENTINEL, 'string', 'mail');
        SystemSetting::clearCache();

        $migration = require database_path('migrations/2026_09_12_000001_encrypt_mail_password_setting.php');

        $migration->up();
        $firstPass = SystemSetting::query()->where('key', 'mail_password')->value('value');

        $migration->up();
        $secondPass = SystemSetting::query()->where('key', 'mail_password')->value('value');

        $this->assertSame($firstPass, $secondPass);
        $this->assertSame(self::SENTINEL, Crypt::decryptString($secondPass));
    }

    public function test_secret_setting_service_round_trips(): void
    {
        $service = app(SecretSettingService::class);

        $service->put('mail_password', self::SENTINEL, 'mail');

        $row = SystemSetting::query()->where('key', 'mail_password')->firstOrFail();
        $this->assertNotSame(self::SENTINEL, $row->value);
        $this->assertSame(self::SENTINEL, $service->get('mail_password'));
    }

    public function test_disallowed_smtp_port_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->putJson('/api/config/email', $this->validPayload(['mail_port' => 8080]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mail_port']);
    }

    public function test_loopback_ip_host_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->putJson('/api/config/email', $this->validPayload(['mail_host' => '127.0.0.1']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mail_host']);
    }

    public function test_localhost_hostname_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->putJson('/api/config/email', $this->validPayload(['mail_host' => 'localhost']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mail_host']);
    }

    public function test_link_local_metadata_host_is_rejected(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->putJson('/api/config/email', $this->validPayload(['mail_host' => '169.254.169.254']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mail_host']);
    }

    public function test_approved_public_relay_passes_validation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->putJson('/api/config/email', $this->validPayload(['mail_host' => 'smtp.gmail.com', 'mail_port' => 587]))
            ->assertOk();
    }
}
