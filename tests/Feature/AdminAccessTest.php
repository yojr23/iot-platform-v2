<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_user_role_management()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('config.user-roles.index'));

        $response->assertForbidden();
    }

    public function test_non_admin_cannot_update_config()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->post(route('config.update'), [
            'app_name' => 'Test',
            'app_url' => 'https://example.com',
            'alert_threshold' => 5,
            'sensor_update_interval' => 2000,
            'mail_enabled' => 1,
        ]);

        $response->assertForbidden();
    }
}
