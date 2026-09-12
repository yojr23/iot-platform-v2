<?php

namespace Tests\Feature\Api;

use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-RT-001: `routes/channels.php` closures for `sensor.{sensorId}`, `alerts`, and
 * `device-status` now delegate to ResourceAccessService (the SAME authority REST policies use),
 * instead of the old hardcoded `true`/`exists()` checks. This locks in that the closures actually
 * consult the service — proven by an unverified user (who passes the old `exists()`/`true` checks)
 * now being rejected, while a verified user still succeeds.
 *
 * Mirrors the pusher-driver bootstrapping in BroadcastChannelAuthorizationTest /
 * Gate10PublicGraphBoundaryTest: the `null` broadcasting driver does not exercise channel
 * authorization at all, so `pusher` is forced with local dummy credentials, and channels.php is
 * re-required so the pusher broadcaster instance actually has the channels registered.
 */
class BroadcastAuthorizationTest extends TestCase
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

    private function tokenFor(User $user): string
    {
        return $user->createToken('broadcast-test', ['read'])->plainTextToken;
    }

    public function test_verified_user_can_authorize_private_sensor_channel(): void
    {
        $user = User::factory()->create();
        $sensor = Sensor::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'private-sensor.'.$sensor->id,
                'socket_id' => '1234.1234',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_unverified_user_cannot_authorize_private_sensor_channel(): void
    {
        $user = User::factory()->unverified()->create();
        $sensor = Sensor::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'private-sensor.'.$sensor->id,
                'socket_id' => '1234.1234',
            ])
            ->assertForbidden();
    }

    public function test_verified_user_can_authorize_private_alerts_channel(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'private-alerts',
                'socket_id' => '1234.1234',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_unverified_user_cannot_authorize_private_alerts_channel(): void
    {
        $user = User::factory()->unverified()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'private-alerts',
                'socket_id' => '1234.1234',
            ])
            ->assertForbidden();
    }

    public function test_verified_user_can_authorize_private_device_status_channel(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'private-device-status',
                'socket_id' => '1234.1234',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_unverified_user_cannot_authorize_private_device_status_channel(): void
    {
        $user = User::factory()->unverified()->create();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'private-device-status',
                'socket_id' => '1234.1234',
            ])
            ->assertForbidden();
    }

    public function test_guest_is_still_denied_private_sensor_channel(): void
    {
        $sensor = Sensor::factory()->create();

        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-sensor.'.$sensor->id,
            'socket_id' => '1234.1234',
        ])->assertUnauthorized();
    }

    public function test_missing_sensor_is_still_denied_for_verified_user(): void
    {
        $user = User::factory()->create();
        $nonexistentSensorId = Sensor::query()->max('id') + 1000;

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/broadcasting/auth', [
                'channel_name' => 'private-sensor.'.$nonexistentSensorId,
                'socket_id' => '1234.1234',
            ])
            ->assertForbidden();
    }
}
