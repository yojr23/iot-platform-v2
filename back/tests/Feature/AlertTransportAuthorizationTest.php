<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN.md Stage 7 / audit.md §12a: alerts, counts and their realtime transport are an authorized
 * capability, not public/guest data. Before this stage `GET /api/alerts/active` was anonymous and
 * `NewAlertTriggered`/`AlertResolved` broadcast on the plain public `Channel('alerts')`. This test
 * locks both fixes down: the REST route now requires `auth:sanctum`, and the realtime channel now
 * requires authorization through `/api/broadcasting/auth` (mirrors the existing private
 * `sensor.{sensorId}` coverage in `BroadcastChannelAuthorizationTest`).
 *
 * Existing code reused: `ApiAlertController::active()` / `AlertService` are unchanged — only the
 * route's middleware and the events' `broadcastOn()` channel type moved. Existing owner
 * retired/delegated: the anonymous top-level `/alerts/active` route is removed, not aliased —
 * PLAN.md Stage 7 does not ask for a compatibility window for a route serving non-public data.
 */
class AlertTransportAuthorizationTest extends TestCase
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
    }

    public function test_guest_is_denied_active_alerts(): void
    {
        Alert::factory()->create(['resolved' => false]);

        $this->getJson('/api/alerts/active')->assertUnauthorized();
    }

    public function test_authenticated_sanctum_token_can_read_active_alerts(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('alerts-test', ['read'])->plainTextToken;
        Alert::factory()->create(['resolved' => false]);

        $this->withToken($token)->getJson('/api/alerts/active')
            ->assertOk()
            ->assertJsonStructure(['count', 'alerts']);
    }

    public function test_guest_is_denied_authorization_for_the_private_alerts_channel(): void
    {
        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-alerts',
            'socket_id' => '1234.1234',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_sanctum_token_can_authorize_the_private_alerts_channel(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('alerts-test', ['read'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-alerts',
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk()->assertJsonStructure(['auth']);
    }
}
