<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_user_role()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($admin)->patch(
            route('config.user-roles.update', $user),
            ['is_admin' => true]
        );

        $response->assertRedirect(route('config.user-roles.index'));
        $this->assertTrue($user->fresh()->is_admin);
    }
}
