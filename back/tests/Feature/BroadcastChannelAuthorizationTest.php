<?php

namespace Tests\Feature;

use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pre-Stage-6 preflight: proves the private `sensor.{sensorId}` channel registered in
 * `routes/channels.php` (`private-sensor.{id}` on the wire) is reachable end to end through the
 * real `/api/broadcasting/auth` route wired by `bootstrap/app.php`'s `withBroadcasting()`
 * (`auth:sanctum`, `prefix => api`), not just the closure in isolation. `App.Models.User.{id}`
 * is exercised the same way as an existing-behavior control.
 *
 * The `null` broadcasting driver (this app's default, see `config/broadcasting.php`) does not
 * exercise channel-authorization logic, so this test forces the `pusher` driver with local dummy
 * credentials for the duration of each test — `Broadcaster::verifyUserCanAccessChannel()` and
 * `PusherBroadcaster::auth()` are pure local HMAC/regex logic (no network call), so this stays a
 * deterministic, offline unit-of-work test while still exercising production auth code, not a
 * reimplementation of it.
 */
class BroadcastChannelAuthorizationTest extends TestCase
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

        // `routes/channels.php` registers its `Broadcast::channel()` callbacks against whichever
        // broadcaster is booted at that time (the app's default `null` driver). Switching
        // `broadcasting.default` to `pusher` above happens after boot, so the pusher broadcaster
        // instance has zero channels registered unless we re-require the routes file now that the
        // config points at `pusher`.
        require base_path('routes/channels.php');
    }

    public function test_unauthenticated_request_is_denied(): void
    {
        $sensor = Sensor::factory()->create();

        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-sensor.'.$sensor->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_authorize_an_existing_sensor_private_channel(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();
        // The route is `auth:sanctum`; `actingAs()` only populates the `web` session guard, so the
        // broadcaster's `$request->user()` would resolve to null. Authenticate with a real Sanctum
        // PAT instead, matching how the SPA actually calls this endpoint (front/src/realtime/echo.js).
        $token = $user->createToken('broadcast-test', ['read'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-sensor.'.$sensor->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk()->assertJsonStructure(['auth']);
    }

    /**
     * The real SPA authorizes private channels via a stored Sanctum PAT sent as
     * `Authorization: Bearer <token>` (front/src/realtime/echo.js), not a session cookie.
     * `actingAs()` above only proves the session-guard path; this proves the actual
     * auth:sanctum token path works end to end through `/api/broadcasting/auth`.
     */
    public function test_sanctum_personal_access_token_can_authorize_a_private_channel(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();
        $token = $user->createToken('broadcast-test', ['read'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-sensor.'.$sensor->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_missing_sensor_is_denied(): void
    {
        $user = User::factory()->create();
        $nonexistentSensorId = Sensor::query()->max('id') + 1000;
        $token = $user->createToken('broadcast-test', ['read'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-sensor.'.$nonexistentSensorId,
            'socket_id' => '1234.1234',
        ]);

        $response->assertForbidden();
    }

    /**
     * Gate 8: `device-status` moved from a public `Channel` to a `PrivateChannel` — a guest must
     * now be denied at `/broadcasting/auth`, mirroring the existing `alerts` coverage in
     * `AlertTransportAuthorizationTest`.
     */
    public function test_guest_is_denied_authorization_for_the_private_device_status_channel(): void
    {
        $response = $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-device-status',
            'socket_id' => '1234.1234',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Gate 8: any authenticated Sanctum PAT (same shape as the SPA's real auth path — see
     * `test_sanctum_personal_access_token_can_authorize_a_private_channel` above) may authorize the
     * private `device-status` channel — no per-device ACL model exists in this app.
     */
    public function test_authenticated_sanctum_token_can_authorize_the_private_device_status_channel(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('device-status-test', ['read'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-device-status',
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk()->assertJsonStructure(['auth']);
    }

    /**
     * Existing-behavior control: `App.Models.User.{id}` (pre-existing per-user private channel,
     * `routes/channels.php`) still authorizes only the owning user, unaffected by this task's
     * addition of `sensor.{sensorId}`.
     */
    public function test_user_channel_still_authorizes_only_the_owning_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = $user->createToken('broadcast-test', ['read'])->plainTextToken;

        $ownChannel = $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$user->id,
            'socket_id' => '1234.1234',
        ]);
        $ownChannel->assertOk()->assertJsonStructure(['auth']);

        $othersChannel = $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$other->id,
            'socket_id' => '1234.1234',
        ]);
        $othersChannel->assertForbidden();
    }
}
