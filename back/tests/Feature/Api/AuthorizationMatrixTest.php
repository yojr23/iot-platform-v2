<?php

namespace Tests\Feature\Api;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_standard_user_with_bearer_token_can_resolve_single_alert(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = $user->createToken('t', ['read'])->plainTextToken;
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        $this->withHeaders($this->bearer($token))
            ->patchJson("/api/alerts/{$alert->id}/resolve")
            ->assertOk();

        $this->assertTrue((bool) $alert->fresh()->resolved);
    }

    public function test_standard_user_with_bearer_token_can_resolve_all_alerts(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $token = $user->createToken('t', ['read'])->plainTextToken;
        Alert::factory()->count(2)->create(['resolved' => false, 'resolved_at' => null]);

        $this->withHeaders($this->bearer($token))
            ->postJson('/api/alerts/resolve-all')
            ->assertOk()
            ->assertJsonPath('resolved_count', 2);

        $this->assertSame(0, Alert::active()->count());
    }

    public function test_unverified_user_with_bearer_token_cannot_resolve_alerts(): void
    {
        $user = User::factory()->unverified()->create(['is_admin' => false]);
        $token = $user->createToken('t', ['read'])->plainTextToken;
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        $this->withHeaders($this->bearer($token))
            ->patchJson("/api/alerts/{$alert->id}/resolve")
            ->assertForbidden();

        $this->assertFalse((bool) $alert->fresh()->resolved);
    }
}
