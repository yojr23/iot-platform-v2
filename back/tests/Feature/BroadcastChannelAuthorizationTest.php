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

        $response = $this->actingAs($user)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-sensor.'.$sensor->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_missing_sensor_is_denied(): void
    {
        $user = User::factory()->create();
        $nonexistentSensorId = Sensor::query()->max('id') + 1000;

        $response = $this->actingAs($user)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-sensor.'.$nonexistentSensorId,
            'socket_id' => '1234.1234',
        ]);

        $response->assertForbidden();
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

        $ownChannel = $this->actingAs($user)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$user->id,
            'socket_id' => '1234.1234',
        ]);
        $ownChannel->assertOk()->assertJsonStructure(['auth']);

        $othersChannel = $this->actingAs($user)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-App.Models.User.'.$other->id,
            'socket_id' => '1234.1234',
        ]);
        $othersChannel->assertForbidden();
    }
}
