<?php

namespace Tests\Feature\Api;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-AUTH-002: Sanctum token abilities become an enforced backend boundary (not just an
 * issued-but-unchecked contract). Ability middleware (`ability:alerts:resolve,admin`) is
 * defense-in-depth layered in front of the pre-existing `AlertPolicy` (SEC-ALERT-001), which
 * stays authoritative and admin-only for alert resolution.
 *
 * Existing code reused: `Alert` factory/model, `User::createToken()` (Sanctum, already used by
 * `AuthApiController::login()`), the `AlertPolicy` from SEC-ALERT-001 (unchanged).
 * These tests mint real Sanctum PATs and send them via the Authorization header rather than
 * `actingAs()`, because Sanctum's `CheckAbilities`/`CheckForAnyAbility` middleware inspect
 * `$request->user()->currentAccessToken()`, which `actingAs()` never attaches.
 */
class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_standard_user_with_read_token_cannot_resolve_single_alert(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = $user->createToken('t', ['read'])->plainTextToken;
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        $this->withHeaders($this->bearer($token))
            ->patchJson("/api/alerts/{$alert->id}/resolve")
            ->assertStatus(403);

        $this->assertFalse((bool) $alert->fresh()->resolved);
    }

    public function test_standard_user_with_read_token_cannot_resolve_all_alerts(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = $user->createToken('t', ['read'])->plainTextToken;
        Alert::factory()->count(2)->create(['resolved' => false, 'resolved_at' => null]);

        $this->withHeaders($this->bearer($token))
            ->postJson('/api/alerts/resolve-all')
            ->assertStatus(403);

        $this->assertSame(2, Alert::active()->count());
    }

    public function test_admin_with_wildcard_token_can_resolve_single_alert(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('t', ['*'])->plainTextToken;
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        $this->withHeaders($this->bearer($token))
            ->patchJson("/api/alerts/{$alert->id}/resolve")
            ->assertOk();

        $this->assertTrue((bool) $alert->fresh()->resolved);
    }

    public function test_admin_with_wildcard_token_can_resolve_all_alerts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('t', ['*'])->plainTextToken;
        Alert::factory()->count(3)->create(['resolved' => false, 'resolved_at' => null]);

        $this->withHeaders($this->bearer($token))
            ->postJson('/api/alerts/resolve-all')
            ->assertOk()
            ->assertJsonPath('resolved_count', 3);

        $this->assertSame(0, Alert::active()->count());
    }

    /**
     * The differentiator: without the ability middleware, `AlertPolicy` alone would let this
     * request through (the user IS an admin), which would make the ability contract issued at
     * login purely decorative. A token deliberately scoped to `['read']` (e.g. a narrowly-scoped
     * PAT an admin issues for a read-only integration) must be rejected at the ability layer
     * even though the underlying user would pass the policy.
     */
    public function test_admin_with_read_only_scoped_token_cannot_resolve_single_alert(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $token = $admin->createToken('t', ['read'])->plainTextToken;
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        $this->withHeaders($this->bearer($token))
            ->patchJson("/api/alerts/{$alert->id}/resolve")
            ->assertStatus(403);

        $this->assertFalse((bool) $alert->fresh()->resolved);
    }

    /**
     * Layered model sanity check: an `alerts:resolve` token ability is necessary but not
     * sufficient. `AlertPolicy::resolve()`/`resolveAll()` (SEC-ALERT-001) still require
     * `is_admin`, so a non-admin user explicitly minted with the `alerts:resolve` ability still
     * gets rejected (by the policy, once the ability check has already passed) — the ability
     * middleware does not widen who may resolve alerts, it only narrows which tokens may attempt
     * to when the policy would otherwise allow them.
     */
    public function test_non_admin_with_explicit_resolve_ability_is_still_blocked_by_policy(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = $user->createToken('t', ['alerts:resolve'])->plainTextToken;
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        $this->withHeaders($this->bearer($token))
            ->patchJson("/api/alerts/{$alert->id}/resolve")
            ->assertStatus(403);

        $this->assertFalse((bool) $alert->fresh()->resolved);
    }
}
