<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BACK-01 (PLAN Mac M3): role management is owned by the RBAC JSON API
 * (ApiUserRoleController), which enforces self-demotion and last-superadmin
 * protection inside a locked transaction. The legacy Blade role write path was
 * retired; these guard the same privilege-escalation invariants through the
 * canonical endpoint.
 */
class SecurityPrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

    private function asSuperadmin(User $user): User
    {
        $user->role_id = Role::where('code', 'superadmin')->value('id');
        $user->saveQuietly();

        return $user;
    }

    public function test_mass_assignment_cannot_set_is_admin_on_user_create(): void
    {
        $user = User::create([
            'name' => 'Escalation Attempt',
            'email' => 'escalation@example.com',
            'password' => 'password',
            'is_admin' => true,
        ]);

        $this->assertFalse((bool) $user->fresh()->is_admin);
    }

    public function test_superadmin_cannot_demote_himself(): void
    {
        $admin = $this->asSuperadmin(User::factory()->create(['is_admin' => true]));
        // A second superadmin so the last-superadmin guard is NOT what blocks this —
        // self-demotion must be rejected on its own.
        $this->asSuperadmin(User::factory()->create(['is_admin' => true]));

        $this->actingAs($admin)->patchJson("/api/users/{$admin->id}/role", [
            'role_code' => 'user',
        ])->assertStatus(422)->assertJsonValidationErrors('role_code');

        $this->assertTrue((bool) $admin->fresh()->is_admin);
    }

    public function test_superadmin_can_demote_a_lower_admin(): void
    {
        $admin = $this->asSuperadmin(User::factory()->create(['is_admin' => true]));
        // Target is a plain `admin` (lower level than superadmin) — a superadmin can
        // manage it. (RBAC canManageRole forbids managing a same-level peer, which is
        // why superadmin↔superadmin demotion is rejected; that peer rule is exercised
        // by test_superadmin_cannot_demote_himself indirectly and by SpaParityApiTest.)
        $targetAdmin = User::factory()->create(['is_admin' => true]);
        $targetAdmin->role_id = Role::where('code', 'admin')->value('id');
        $targetAdmin->saveQuietly();

        $this->actingAs($admin)->patchJson("/api/users/{$targetAdmin->id}/role", [
            'role_code' => 'user',
        ])->assertOk();

        $this->assertTrue((bool) $admin->fresh()->is_admin);
        $this->assertFalse((bool) $targetAdmin->fresh()->is_admin);
    }

    public function test_non_admin_cannot_elevate_a_user_to_admin_even_via_model_update(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create(['is_admin' => false]);

        $this->actingAs($nonAdmin);
        $this->expectException(AuthorizationException::class);

        $target->is_admin = true;
        $target->save();
    }
}
