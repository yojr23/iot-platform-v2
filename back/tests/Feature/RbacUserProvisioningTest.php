<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacUserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_new_user_receives_the_standard_role_when_no_role_is_supplied(): void
    {
        $user = User::factory()->create();

        $this->assertSame('user', $user->fresh()->role?->code);
    }

    public function test_legacy_admin_fixture_receives_the_admin_role_during_the_transition(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->assertSame('admin', $user->fresh()->role?->code);
    }

    public function test_standard_user_has_the_alert_resolution_permission(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->can('alert.resolve'));
    }

    public function test_dynamic_role_permission_management_capability_is_not_seeded(): void
    {
        $this->assertDatabaseMissing('permissions', [
            'code' => 'role.permissions.manage',
        ]);

        $superadmin = User::factory()->create([
            'is_admin' => true,
        ]);

        $superadmin->role_id = \App\Models\Role::where('code', 'superadmin')->value('id');
        $superadmin->saveQuietly();

        $this->assertNotContains(
            'role.permissions.manage',
            $superadmin->fresh()->getAllPermissions()->all()
        );
    }
}
