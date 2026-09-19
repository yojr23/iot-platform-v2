<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BACK-01 (PLAN Mac M3): admin-only product/config surfaces are owned by the
 * permission-gated JSON API (legacy Blade config/roles/metrics writes retired).
 * These guard that a non-privileged user is denied config/role management while
 * still reaching the endpoints their role permits.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_user_role_management(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        // /api/users is gated by permission:user.view — a standard user lacks it.
        $this->actingAs($user)->getJson('/api/users')->assertForbidden();
    }

    public function test_non_admin_cannot_update_config(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->putJson('/api/config/alerts', [
            'alert_threshold' => 5,
            'mail_enabled' => true,
        ])->assertForbidden();
    }

    public function test_standard_user_can_access_metrics_reads(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        // /api/metrics only needs auth:sanctum+verified, not admin.
        $this->actingAs($user)->getJson('/api/metrics')->assertOk();
    }

    public function test_non_admin_cannot_view_admin_configuration(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        // config read is gated by permission:system_setting.view.
        $this->actingAs($user)->getJson('/api/config/alerts')->assertForbidden();
    }

    public function test_internal_api_metrics_require_admin_authentication(): void
    {
        $this->getJson('/api/internal/metrics/api-performance')
            ->assertUnauthorized();

        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->getJson('/api/internal/metrics/api-performance')
            ->assertForbidden();
    }
}
