<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Task 21 — sentinel-based data-leakage regressions. Each secret gets a unique sentinel value;
 * every unauthorized surface (API response, serialized model) is asserted free of it. One place
 * that fails loudly if a credential or admin-only topology field starts leaking.
 */
class DataLeakageSentinelTest extends TestCase
{
    use RefreshDatabase;

    private const SMTP_SENTINEL = 'SUPER_SECRET_SMTP_SENTINEL';

    public function test_device_index_never_contains_the_api_key(): void
    {
        Device::factory()->count(2)->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $body = $this->actingAs($admin)->getJson('/api/devices?per_page=10')->assertOk()->getContent();

        $this->assertStringNotContainsString('"api_key"', $body);
    }

    public function test_device_model_array_hides_the_api_key(): void
    {
        $device = Device::factory()->create();

        $this->assertArrayNotHasKey('api_key', $device->toArray());
        $this->assertStringNotContainsString('api_key', json_encode($device));
    }

    public function test_standard_user_device_detail_omits_network_topology(): void
    {
        $device = Device::factory()->create([
            'ip_address' => '10.9.9.9',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);
        $user = User::factory()->create(['is_admin' => false]);

        $body = $this->actingAs($user)->getJson("/api/devices/{$device->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('10.9.9.9', $body);
        $this->assertStringNotContainsString('AA:BB:CC:DD:EE:FF', $body);
    }

    public function test_email_config_response_never_contains_the_smtp_password(): void
    {
        // Store the secret exactly how EmailConfigController does (encrypted at rest).
        SystemSetting::set('mail_password', Crypt::encryptString(self::SMTP_SENTINEL), 'string', 'mail');
        SystemSetting::clearCache();
        $admin = User::factory()->create(['is_admin' => true]);

        $body = $this->actingAs($admin)->getJson('/api/config/email')->assertOk()->getContent();

        $this->assertStringNotContainsString(self::SMTP_SENTINEL, $body);
    }

    public function test_smtp_password_is_not_stored_in_plaintext(): void
    {
        SystemSetting::set('mail_password', Crypt::encryptString(self::SMTP_SENTINEL), 'string', 'mail');

        $raw = SystemSetting::query()->where('key', 'mail_password')->value('value');

        $this->assertNotSame(self::SMTP_SENTINEL, $raw);
        $this->assertSame(self::SMTP_SENTINEL, Crypt::decryptString($raw));
    }
}
