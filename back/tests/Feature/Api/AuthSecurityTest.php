<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * SEC-TOKEN-001: existing Sanctum personal access tokens must be revoked whenever the
 * credential/role they were minted under changes underneath them — password reset and
 * admin role downgrade (or any role change) being the two cases in scope here.
 *
 * Note: Laravel's `sanctum` RequestGuard memoizes the resolved user on the guard singleton
 * for the lifetime of the test's application instance. Because each test below drives more
 * than one authenticated request (and, in the second test, more than one actor) through that
 * same singleton, we call `Auth::forgetGuards()` between requests so each one re-resolves the
 * bearer token against the current DB state instead of returning a stale cached user. This is
 * a test-harness-only concern; real HTTP requests each get a fresh guard.
 */
class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_revokes_existing_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-revoke@gmail.com',
            'password' => 'OldPassword123!',
        ]);

        $token = $user->createToken('spa-client', ['read'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/profile')
            ->assertOk();

        $resetToken = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => 'NuevaClaveSegura123!',
            'password_confirmation' => 'NuevaClaveSegura123!',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('NuevaClaveSegura123!', $user->password));

        Auth::forgetGuards();

        $this->withToken($token)
            ->getJson('/api/profile')
            ->assertUnauthorized();

        $this->assertSame(0, PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->count());
    }

    public function test_admin_downgrade_revokes_target_user_tokens(): void
    {
        // Two admins so demoting one still satisfies the "at least one admin" guard.
        $actingAdmin = User::factory()->create(['is_admin' => true]);
        $targetAdmin = User::factory()->create(['is_admin' => true]);

        $targetToken = $targetAdmin->createToken('admin-client', ['*'])->plainTextToken;

        $this->withToken($targetToken)
            ->getJson('/api/users')
            ->assertOk();

        $actingToken = $actingAdmin->createToken('acting-admin-client', ['*'])->plainTextToken;

        Auth::forgetGuards();

        $this->withToken($actingToken)
            ->patchJson("/api/users/{$targetAdmin->id}/role", [
                'is_admin' => false,
            ])
            ->assertOk();

        $this->assertFalse((bool) $targetAdmin->fresh()->is_admin);

        Auth::forgetGuards();

        $this->withToken($targetToken)
            ->getJson('/api/users')
            ->assertUnauthorized();

        $this->assertSame(0, PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $targetAdmin->id)
            ->count());
    }

    public function test_web_admin_downgrade_revokes_target_user_tokens(): void
    {
        // Blade/web counterpart of the API role-change controller — same vulnerability class,
        // same fix (App\Http\Controllers\UserRoleController::update()).
        $actingAdmin = User::factory()->create(['is_admin' => true]);
        $targetAdmin = User::factory()->create(['is_admin' => true]);

        $targetAdmin->createToken('admin-client', ['*']);

        $this->actingAs($actingAdmin)
            ->patch("/config/user-roles/{$targetAdmin->id}", [
                'is_admin' => false,
            ])
            ->assertRedirect();

        $this->assertFalse((bool) $targetAdmin->fresh()->is_admin);

        $this->assertSame(0, PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $targetAdmin->id)
            ->count());
    }
}
