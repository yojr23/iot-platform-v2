<?php

namespace Tests\Feature\Api;

use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 20 — realtime channel authorization regressions beyond the core matrix in
 * BroadcastAuthorizationTest: admin acceptance, and that revoking the token (as password
 * reset / role downgrade do) blocks any subsequent private channel auth. /api/broadcasting/auth
 * is authoritative — the frontend is never the control.
 *
 * Same pusher bootstrap as BroadcastAuthorizationTest: the `null` driver skips channel
 * authorization entirely, so pusher is forced with dummy creds and channels.php re-required.
 */
class RealtimeAuthorizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app-id',
            'broadcasting.connections.pusher.options.cluster' => 'mt1',
            'broadcasting.connections.pusher.options.host' => 'api-mt1.pusher.com',
            'broadcasting.connections.pusher.options.useTLS' => true,
        ]);
        require base_path('routes/channels.php');
    }

    private function authorize(string $token, string $channel): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'channel_name' => $channel,
            'socket_id' => '1234.1234',
        ]);
    }

    public function test_admin_can_authorize_private_channels(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sensor = Sensor::factory()->create();
        $token = $admin->createToken('rt', ['*'])->plainTextToken;

        $this->authorize($token, 'private-sensor.'.$sensor->id)->assertOk()->assertJsonStructure(['auth']);
        $this->app['auth']->forgetGuards();
        $this->authorize($token, 'private-alerts')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->authorize($token, 'private-device-status')->assertOk();
    }

    public function test_revoked_token_can_no_longer_authorize_a_private_channel(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();
        $token = $user->createToken('rt', ['read'])->plainTextToken;

        // Works before revocation.
        $this->authorize($token, 'private-sensor.'.$sensor->id)->assertOk();

        // Password reset / role downgrade revoke all tokens (SEC-TOKEN-001).
        $user->tokens()->delete();
        $this->app['auth']->forgetGuards();

        // The same bearer token is now unauthenticated → 401/403, never a successful auth.
        $response = $this->authorize($token, 'private-sensor.'.$sensor->id);
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }
}
