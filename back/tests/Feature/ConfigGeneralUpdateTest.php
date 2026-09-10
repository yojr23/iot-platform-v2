<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfigGeneralUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_general_config_and_it_round_trips_via_runtime(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($user)->putJson('/api/config/general', [
            'app_name' => 'SINOA Lab',
            'app_url' => 'https://sinoa.example.com',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.app_name', 'SINOA Lab')
            ->assertJsonPath('data.app_url', 'https://sinoa.example.com')
            ->assertJsonPath('message', 'Configuración general actualizada.');

        $this->assertSame('SINOA Lab', SystemSetting::get('app_name'));
        $this->assertSame('https://sinoa.example.com', SystemSetting::get('app_url'));

        $this->actingAs($user)->getJson('/api/config/runtime')
            ->assertOk()
            ->assertJsonPath('app_url', 'https://sinoa.example.com');
    }

    public function test_non_admin_cannot_update_general_config(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->putJson('/api/config/general', [
            'app_name' => 'SINOA Lab',
            'app_url' => 'https://sinoa.example.com',
        ])->assertForbidden();
    }

    public function test_guest_cannot_update_general_config(): void
    {
        $this->putJson('/api/config/general', [
            'app_name' => 'SINOA Lab',
            'app_url' => 'https://sinoa.example.com',
        ])->assertStatus(401);
    }

    public function test_validation_rejects_missing_app_name(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)->putJson('/api/config/general', [
            'app_url' => 'https://sinoa.example.com',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['app_name']);
    }

    public function test_validation_rejects_non_url_app_url(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)->putJson('/api/config/general', [
            'app_name' => 'SINOA Lab',
            'app_url' => 'not-a-url',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['app_url']);
    }
}
