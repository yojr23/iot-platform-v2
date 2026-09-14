<?php

namespace Tests\Unit;

use App\Services\Ingestion\DeadLetterStreamService;
use Illuminate\Redis\Connections\Connection;
use Mockery;
use Tests\TestCase;

class DeadLetterStreamServiceTest extends TestCase
{
    public function test_inspect_normalizes_flat_redis_entries(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('executeRaw')->once()->with([
            'XRANGE', 'iot.dead-letter-events', '-', '+', 'COUNT', '10',
        ])->andReturn([
            ['1710000000000-0', ['orig_id', '1-0', 'reason', 'bad payload', 'attempts', '5']],
        ]);

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('client')->andReturn($client);

        $service = new DeadLetterStreamService($connection, 'iot.dead-letter-events');

        $this->assertSame([
            [
                'id' => '1710000000000-0',
                'fields' => ['orig_id' => '1-0', 'reason' => 'bad payload', 'attempts' => '5'],
            ],
        ], $service->inspect(10));
    }

    public function test_inspect_normalizes_associative_redis_entries(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('executeRaw')->once()->andReturn([
            '1710000000000-0' => ['orig_id' => '1-0', 'reason' => 'bad payload', 'attempts' => '5'],
        ]);

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('client')->andReturn($client);

        $service = new DeadLetterStreamService($connection, 'iot.dead-letter-events');

        $this->assertSame([
            [
                'id' => '1710000000000-0',
                'fields' => ['orig_id' => '1-0', 'reason' => 'bad payload', 'attempts' => '5'],
            ],
        ], $service->inspect());
    }

    public function test_replay_is_rejected_for_an_unapproved_source_stream(): void
    {
        $connection = Mockery::mock(Connection::class);
        $service = new DeadLetterStreamService(
            $connection,
            'iot.dead-letter-events',
            ['iot.raw-events'],
        );

        $this->expectException(\InvalidArgumentException::class);
        $service->replay('1-0', 'arbitrary.stream', 'operator');
    }

    public function test_replay_returns_idempotent_result_from_atomic_redis_operation(): void
    {
        $client = Mockery::mock();
        $client->shouldReceive('executeRaw')->once()->withArgs(function (array $args): bool {
            return $args[0] === 'EVAL'
                && $args[1] !== ''
                && $args[2] === '4'
                && $args[3] === 'iot.dead-letter-events'
                && $args[4] === 'iot.raw-events'
                && $args[6] === 'iot.dead-letter-replays'
                && $args[7] === '1710000000000-0'
                && $args[8] === 'operator';
        })->andReturn(['replayed', '1710000001000-0']);

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('client')->andReturn($client);

        $service = new DeadLetterStreamService(
            $connection,
            'iot.dead-letter-events',
            ['iot.raw-events'],
        );

        $this->assertSame([
            'status' => 'replayed',
            'replay_id' => '1710000001000-0',
            'source' => 'iot.raw-events',
            'original_id' => '1710000000000-0',
        ], $service->replay('1710000000000-0', 'iot.raw-events', 'operator'));
    }
}
