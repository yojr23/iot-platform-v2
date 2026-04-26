<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_and_get_cast_values_by_type(): void
    {
        SystemSetting::set('threshold', 15, 'integer', 'alerts');
        SystemSetting::set('mail_enabled', 1, 'boolean', 'mail');
        SystemSetting::set('channels', json_encode(['email', 'sms']), 'json', 'alerts');

        $this->assertSame(15, SystemSetting::get('threshold'));
        $this->assertTrue(SystemSetting::get('mail_enabled'));
        $this->assertSame(['email', 'sms'], SystemSetting::get('channels'));
    }

    public function test_get_by_group_reflects_recent_updates_without_manual_cache_clear(): void
    {
        SystemSetting::set('app_name', 'IOT v1', 'string', 'general');

        $firstSnapshot = SystemSetting::getByGroup('general');
        $this->assertSame('IOT v1', $firstSnapshot['app_name']);

        SystemSetting::set('app_name', 'IOT v2', 'string', 'general');

        $secondSnapshot = SystemSetting::getByGroup('general');
        $this->assertSame('IOT v2', $secondSnapshot['app_name']);
    }

    public function test_set_updates_existing_key_without_creating_duplicates(): void
    {
        SystemSetting::set('mail_to', 'first@example.test', 'string', 'mail');
        SystemSetting::set('mail_to', 'second@example.test', 'string', 'mail');

        $this->assertSame(1, SystemSetting::query()->where('key', 'mail_to')->count());
        $this->assertSame('second@example.test', SystemSetting::get('mail_to'));
    }
}
