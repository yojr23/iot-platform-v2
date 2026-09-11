<?php

namespace Tests\Unit;

use App\Models\DomainEventOutbox;
use App\Services\Ingestion\DomainEventPublisher;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

/**
 * Freezes the publisher XADD transport contract.
 *
 * History: publishers first flattened fields into positional args for phpredis `xAdd` (arity bug),
 * then were "fixed" to the facade `Redis::command('xadd', [stream,'*',assocFields])`. That facade
 * path silently PREPENDS Laravel's Redis key prefix, so XADD landed on `<prefix>iot.raw-events`
 * while every consumer + Debezium read the UNPREFIXED `iot.raw-events` — a live delivery break the
 * mocked CDC tests never caught (see PublisherStreamPrefixTest for the real-Redis proof).
 *
 * Contract now: publishers issue a RAW, unprefixed XADD via the connection client (rawCommand /
 * executeRaw) with flattened positional field pairs — the exact wire form the consumers read. This
 * test asserts the publisher takes that raw path and NEVER the prefixing `Redis::command('xadd')`.
 */
class EventPublisherXaddShapeTest extends TestCase
{
    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    public function test_domain_event_publisher_uses_raw_unprefixed_xadd_not_the_prefixing_facade(): void
    {
        $captured = null;

        // Fake phpredis client: capture the raw XADD args, prove it's the raw path.
        $client = Mockery::mock(\Redis::class);
        $client->shouldReceive('rawCommand')
            ->once()
            ->andReturnUsing(function (...$args) use (&$captured) {
                $captured = $args;

                return '1-0';
            });

        $connection = Mockery::mock(\Illuminate\Redis\Connections\Connection::class);
        $connection->shouldReceive('client')->andReturn($client);

        Redis::shouldReceive('connection')->with('default')->andReturn($connection);
        // The prefixing facade path must NOT be used.
        Redis::shouldReceive('command')->with('xadd', Mockery::any())->never();

        $outbox = new DomainEventOutbox([
            'event_type' => 'sensor.reading.created',
            'aggregate_type' => 'sensor_reading',
            'aggregate_id' => '7',
            'payload' => ['reading_id' => 7, 'value' => 1.5],
        ]);
        $outbox->id = 42;

        $result = (new DomainEventPublisher())->publish($outbox);

        $this->assertTrue($result);
        // Raw wire form: ['XADD', $stream, '*', 'k1','v1','k2','v2', ...] — flattened, unprefixed.
        $this->assertSame('XADD', $captured[0]);
        $this->assertSame('*', $captured[2]);
        $this->assertContains('event_type', $captured, 'field keys must be flattened positional args');
        $this->assertContains('sensor.reading.created', $captured);
        // Stream name must be the configured value with NO Laravel key prefix.
        $this->assertSame((string) config('app.domain_events_stream', 'iot.domain-events'), $captured[1]);
    }
}
