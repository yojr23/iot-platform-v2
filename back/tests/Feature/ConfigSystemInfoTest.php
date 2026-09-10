<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C4 (front_rebuild_plan/MAIN_PARITY_GAPS_PLAN.md): GET /api/config/system-info, admin-only,
 * read-only introspection alongside the existing /api/config/alerts /general /email routes.
 * GAP: could not run `php artisan test --filter=ConfigSystemInfoTest` in this session
 * (no php/composer available) — run it on an operator machine before merging.
 */
class ConfigSystemInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_system_info(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->getJson('/api/config/system-info');

        $response->assertOk()
            ->assertJsonPath('data.php_version', PHP_VERSION)
            ->assertJsonPath('data.laravel_version', app()->version())
            ->assertJsonPath('data.environment', app()->environment())
            ->assertJsonPath('data.db_driver', config('database.default'));
    }

    public function test_non_admin_cannot_read_system_info(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->getJson('/api/config/system-info')
            ->assertStatus(403);
    }

    public function test_guest_cannot_read_system_info(): void
    {
        $this->getJson('/api/config/system-info')
            ->assertStatus(401);
    }
}
