<?php

namespace Tests\Unit;

use App\Services\Ingestion\DeadLetterStreamService;
use Illuminate\Redis\Connections\Connection;
use Mockery;
use Tests\TestCase;

class DeadLetterStreamServiceTest extends TestCase
{
    private function fakePredisClient(mixed $reply): object
    {
        return new class($reply)
        {
            /** @var list<array<int,string>> */
            public array $commands = [];

            public function __construct(private mixed $reply)
            {
            }

            public function executeRaw(array $args): mixed
            {
                $this->commands[] = $args;

                return $this->reply;
            }
        };
    }

    public function test_inspect_normalizes_flat_redis_entries(): void
    {
        $client = $this->fakePredisClient([
            ['1710000000000-0', ['orig_id', '1-0', 'reason', 'bad payload', 'attempts', '5']],
        ]);

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('client')->andReturn($client);

        $service = new DeadLetterStreamService($connection, 'iot.dead-letter-events');

        $entries = $service->inspect(10);

        $this->assertSame([
            [
                'id' => '1710000000000-0',
                'fields' => ['orig_id' => '1-0', 'reason' => 'bad payload', 'attempts' => '5'],
            ],
        ], $entries);
        $this->assertSame([['XRANGE', 'iot.dead-letter-events', '-', '+', 'COUNT', '10']], $client->commands);
    }

    public function test_inspect_normalizes_associative_redis_entries(): void
    {
        $client = $this->fakePredisClient([
            '1710000000000-0' => ['orig_id' => '1-0', 'reason' => 'bad payload', 'attempts' => '5'],
        ]);

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('client')->andReturn($client);

        $service = new DeadLetterStreamService($connection, 'iot.dead-letter-events');

        $entries = $service->inspect();

        $this->assertSame([
            [
                'id' => '1710000000000-0',
                'fields' => ['orig_id' => '1-0', 'reason' => 'bad payload', 'attempts' => '5'],
            ],
        ], $entries);
        $this->assertSame([['XRANGE', 'iot.dead-letter-events', '-', '+', 'COUNT', '50']], $client->commands);
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

    public function test_replay_rejects_a_malformed_stored_payload_reported_by_the_atomic_operation(): void
    {
        $client = $this->fakePredisClient(['malformed_payload', '']);

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('client')->andReturn($client);

        $service = new DeadLetterStreamService(
            $connection,
            'iot.dead-letter-events',
            ['iot.raw-events'],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('malformed payload');

        $service->replay('1710000000000-0', 'iot.raw-events', 'operator');
    }

    public function test_replay_returns_idempotent_result_from_atomic_redis_operation(): void
    {
        $client = $this->fakePredisClient(['replayed', '1710000001000-0']);

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('client')->andReturn($client);

        $service = new DeadLetterStreamService(
            $connection,
            'iot.dead-letter-events',
            ['iot.raw-events'],
        );

        $result = $service->replay('1710000000000-0', 'iot.raw-events', 'operator');

        $this->assertSame([
            'status' => 'replayed',
            'replay_id' => '1710000001000-0',
            'source' => 'iot.raw-events',
            'original_id' => '1710000000000-0',
        ], $result);
        $this->assertCount(1, $client->commands);
        $this->assertSame('EVAL', $client->commands[0][0]);
        $this->assertNotSame('', $client->commands[0][1]);
        $this->assertSame([
            '4',
            'iot.dead-letter-events',
            'iot.raw-events',
            'iot:dlq:replayed:1710000000000-0',
            'iot.dead-letter-replays',
            '1710000000000-0',
            'operator',
        ], array_slice($client->commands[0], 2));
    }
}
