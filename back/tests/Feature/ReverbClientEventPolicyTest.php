<?php

namespace Tests\Feature;

use Laravel\Reverb\Application;
use Laravel\Reverb\Connection as ReverbConnection;
use Laravel\Reverb\Contracts\WebSocketConnection;
use Laravel\Reverb\Protocols\Pusher\ClientEvent;
use Tests\TestCase;

class ReverbClientEventPolicyTest extends TestCase
{
    public function test_client_events_are_disabled_by_default(): void
    {
        $this->assertSame(
            'none',
            config('reverb.apps.apps.0.accept_client_events_from')
        );
    }

    public function test_vendor_client_event_handler_rejects_client_events_when_disabled(): void
    {
        $application = new Application(
            id: 'test-app',
            key: 'test-key',
            secret: 'test-secret',
            pingInterval: 60,
            activityTimeout: 30,
            allowedOrigins: ['localhost'],
            maxMessageSize: 10_000,
            acceptClientEventsFrom: config('reverb.apps.apps.0.accept_client_events_from'),
        );

        $socket = new class implements WebSocketConnection
        {
            /** @var list<string> */
            public array $messages = [];

            public function id(): int|string
            {
                return 1;
            }

            public function send(mixed $message): void
            {
                $this->messages[] = (string) $message;
            }

            public function close(mixed $message = null): void {}
        };

        $connection = new ReverbConnection($socket, $application, 'http://localhost:4173');

        ClientEvent::handle($connection, [
            'event' => 'client-sinoa-probe',
            'channel' => 'private-sinoa-test',
            'data' => ['probe' => true],
        ]);

        $this->assertCount(1, $socket->messages);

        $message = json_decode($socket->messages[0], true, 512, JSON_THROW_ON_ERROR);
        $error = json_decode($message['data'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('pusher:error', $message['event']);
        $this->assertSame(4301, $error['code']);
        $this->assertSame('The app does not have client messaging enabled.', $error['message']);
    }
}
