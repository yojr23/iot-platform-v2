<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityPrivilegeEscalationTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_admin_cannot_demote_himself_from_user_role_management(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->patch(route('config.user-roles.update', $admin), [
            'is_admin' => false,
        ]);

        $response->assertSessionHasErrors('is_admin');
        $this->assertTrue((bool) $admin->fresh()->is_admin);
        $this->assertTrue((bool) $otherAdmin->fresh()->is_admin);
    }

    public function test_admin_can_demote_another_admin_when_platform_keeps_at_least_one_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $targetAdmin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->patch(route('config.user-roles.update', $targetAdmin), [
            'is_admin' => false,
        ]);

        $response->assertRedirect(route('config.user-roles.index'));
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
