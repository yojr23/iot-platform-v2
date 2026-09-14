<?php

namespace Tests\Feature\Api;

use App\Models\Alert;
use App\Models\DomainEventOutbox;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-ALERT-001: alert *lifecycle mutations* (resolve / resolve-all) are an admin-only capability.
 * Read endpoints (index/active/unresolved/show) stay open to any verified user — unchanged, not
 * covered here (see existing Alert* feature tests for those).
 */
class AlertAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_resolve_single_alert(): void
    {
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        $this->patchJson("/api/alerts/{$alert->id}/resolve")->assertUnauthorized();
    }

    public function test_guest_cannot_resolve_all_alerts(): void
    {
        Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        $this->postJson('/api/alerts/resolve-all')->assertUnauthorized();
    }

    public function test_standard_verified_user_can_resolve_single_alert(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('code', 'user')->value('id')]);
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        $this->actingAs($user)
            ->patchJson("/api/alerts/{$alert->id}/resolve")
            ->assertOk();

        $this->assertTrue((bool) $alert->fresh()->resolved);
    }

    public function test_standard_verified_user_can_resolve_all_alerts(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('code', 'user')->value('id')]);
        Alert::factory()->count(2)->create(['resolved' => false, 'resolved_at' => null]);

        $this->actingAs($user)
            ->postJson('/api/alerts/resolve-all')
            ->assertOk()
            ->assertJsonPath('resolved_count', 2);

        $this->assertSame(0, Alert::active()->count());
    }

    public function test_admin_can_resolve_single_alert_and_emits_domain_fact(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        $this->actingAs($admin)
            ->patchJson("/api/alerts/{$alert->id}/resolve")
            ->assertOk();

        $this->assertTrue((bool) $alert->fresh()->resolved);
        $this->assertSame(1, DomainEventOutbox::query()
            ->where('event_type', 'alert.resolved')
            ->where('aggregate_type', 'alert')
            ->where('aggregate_id', (string) $alert->id)
            ->count());
    }

    public function test_admin_can_resolve_all_alerts_and_emits_domain_facts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Alert::factory()->count(3)->create(['resolved' => false, 'resolved_at' => null]);

        $this->actingAs($admin)
            ->postJson('/api/alerts/resolve-all')
            ->assertOk()
            ->assertJsonPath('resolved_count', 3);

        $this->assertSame(0, Alert::active()->count());
        $this->assertSame(3, DomainEventOutbox::query()->where('event_type', 'alert.resolved')->count());
    }
}
