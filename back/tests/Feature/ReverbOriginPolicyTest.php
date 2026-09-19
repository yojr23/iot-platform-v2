<?php

namespace Tests\Feature;

use Laravel\Reverb\Application;
use Laravel\Reverb\Connection as ReverbConnection;
use Laravel\Reverb\Contracts\WebSocketConnection;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\EventHandler;
use Laravel\Reverb\Protocols\Pusher\Exceptions\InvalidOrigin;
use Laravel\Reverb\Protocols\Pusher\Server as PusherServer;
use ReflectionMethod;
use Tests\TestCase;

/**
 * EVT-01 (release blocker): `config/reverb.php` hardcoded `'allowed_origins' => ['*']`, letting
 * any origin open a websocket connection to this app's broadcaster. Fixed to build the allowlist
 * from `REVERB_ALLOWED_ORIGINS` (comma-separated), falling back to the two known local dev origins
 * when unset — never `'*'`.
 *
 * These tests drive the REAL vendor check
 * (`Laravel\Reverb\Protocols\Pusher\Server::verifyOrigin()`, invoked via reflection since it's
 * protected) instead of re-implementing origin comparison, so a future change that reintroduces the
 * wildcard or breaks the vendor origin-matching contract (host-only comparison via
 * `parse_url(..., PHP_URL_HOST)` + `Str::is()`) fails this test, not just a hand-rolled assertion.
 */
class ReverbOriginPolicyTest extends TestCase
{
    private function verifyOriginAllowed(array $allowedOrigins, ?string $origin): bool
    {
        $application = new Application(
            id: 'test-app',
            key: 'test-key',
            secret: 'test-secret',
            pingInterval: 60,
            activityTimeout: 30,
            allowedOrigins: $allowedOrigins,
            maxMessageSize: 10_000,
        );

        $socket = new class implements WebSocketConnection
        {
            public function id(): int|string
            {
                return 1;
            }

            public function send(mixed $message): void {}

            public function close(mixed $message = null): void {}
        };

        $connection = new ReverbConnection($socket, $application, $origin);

        $server = new PusherServer(
            \Mockery::mock(ChannelManager::class),
            \Mockery::mock(EventHandler::class)
        );

        $method = new ReflectionMethod($server, 'verifyOrigin');
        $method->setAccessible(true);

        try {
            $method->invoke($server, $connection);

            return true;
        } catch (InvalidOrigin) {
            return false;
        }
    }

    public function test_configured_allowed_origins_never_contain_the_wildcard(): void
    {
        $allowedOrigins = config('reverb.apps.apps.0.allowed_origins');

        $this->assertNotEmpty($allowedOrigins);
        $this->assertNotContains('*', $allowedOrigins);
    }

    public function test_configured_local_dev_origins_are_accepted(): void
    {
        $allowedOrigins = config('reverb.apps.apps.0.allowed_origins');

        $this->assertTrue($this->verifyOriginAllowed($allowedOrigins, 'http://localhost:4173'));
        $this->assertTrue($this->verifyOriginAllowed($allowedOrigins, 'http://127.0.0.1:4173'));
    }

    public function test_unauthorized_origin_is_rejected(): void
    {
        $allowedOrigins = config('reverb.apps.apps.0.allowed_origins');

        $this->assertFalse($this->verifyOriginAllowed($allowedOrigins, 'https://evil.example.com'));
    }
}
