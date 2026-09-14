<?php

namespace Tests\Feature;

use App\Services\Ingestion\DeadLetterStreamService;
use Illuminate\Redis\RedisManager;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class DeadLetterCommandsTest extends TestCase
{
    private RedisManager $redis;
    private Connection $redisConnection;
    private string $sourceStream = 'test.dlq.replay-source';
    private string $otherSourceStream = 'test.dlq.other-source';
    private string $deadLetterStream = 'test.dead-letter-events';
    private string $auditStream = 'test.dead-letter-replays';

    private function setUpReplayRedis(): void
    {
        $host = env('TEST_REDIS_HOST', '127.0.0.1');
        $port = (int) env('TEST_REDIS_PORT', 6399);

        $this->redis = new RedisManager(app(), 'predis', [
            'client' => 'predis',
            'default' => ['host' => $host, 'port' => $port, 'database' => 15],
        ]);

        try {
            $this->redisConnection = $this->redis->connection('default');
            $this->redisConnection->client()->executeRaw(['PING']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('ENVIRONMENT_CONSTRAINT: no Redis at '.$host.':'.$port.' ('.$e->getMessage().')');
        }

        foreach ([$this->sourceStream, $this->otherSourceStream, $this->deadLetterStream, $this->auditStream] as $stream) {
            $this->redisConnection->client()->executeRaw(['DEL', $stream]);
        }
    }

    public function test_replay_requires_explicit_confirmation(): void
    {
        $this->artisan('dlq:replay', ['id' => '1-0', '--source' => 'iot.raw-events'])
            ->expectsOutputToContain('Refusing to replay without --yes')
            ->assertExitCode(\Symfony\Component\Console\Command\Command::INVALID);
    }

    public function test_inspect_can_render_json(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('client')->once()->andReturn(new class
        {
            public function executeRaw(array $args): array
            {
                return [];
            }
        });
        Redis::shouldReceive('connection')->with('default')->once()->andReturn($connection);

        $this->artisan('dlq:inspect', ['--json' => true])
            ->expectsOutput('[]')
            ->assertExitCode(0);
    }

    public function test_replay_uses_stored_payload_after_the_original_source_entry_is_removed(): void
    {
        $this->setUpReplayRedis();

        $sourceId = $this->redisConnection->client()->executeRaw([
            'XADD', $this->sourceStream, '*',
            'event_id', '42',
            'event_type', 'raw.sensor.received',
            'event_version', '1',
        ]);
        $payloadJson = json_encode([
            'event_id' => '42',
            'event_type' => 'raw.sensor.received',
            'event_version' => '1',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $deadLetterId = $this->redisConnection->client()->executeRaw([
            'XADD', $this->deadLetterStream, '*',
            'orig_stream', $this->sourceStream,
            'orig_id', $sourceId,
            'reason', 'test failure',
            'attempts', '5',
            'payload_json', $payloadJson,
            'event_id', '42',
            'failed_at', '2026-09-14T00:00:00+00:00',
        ]);
        $this->redisConnection->client()->executeRaw(['XDEL', $this->sourceStream, $sourceId]);

        $service = $this->service();

        $firstReplay = $service->replay($deadLetterId, $this->sourceStream, 'operator');
        $secondReplay = $service->replay($deadLetterId, $this->sourceStream, 'operator');

        $this->assertSame('replayed', $firstReplay['status']);
        $this->assertSame('already_replayed', $secondReplay['status']);
        $this->assertSame($firstReplay['replay_id'], $secondReplay['replay_id']);
        $this->assertSame([
            'event_id' => '42',
            'event_type' => 'raw.sensor.received',
            'event_version' => '1',
        ], $this->streamFields($this->sourceStream, (string) $firstReplay['replay_id']));
    }

    public function test_replay_rejects_a_target_that_does_not_match_the_stored_source_stream(): void
    {
        $this->setUpReplayRedis();

        $deadLetterId = $this->redisConnection->client()->executeRaw([
            'XADD', $this->deadLetterStream, '*',
            'orig_stream', $this->sourceStream,
            'orig_id', '1-0',
            'reason', 'test failure',
            'attempts', '1',
            'payload_json', '{"event_id":"42"}',
            'failed_at', '2026-09-14T00:00:00+00:00',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('source mismatch');

        $this->service()->replay($deadLetterId, $this->otherSourceStream, 'operator');
    }

    public function test_replay_rejects_malformed_stored_payload_json(): void
    {
        $this->setUpReplayRedis();

        $deadLetterId = $this->redisConnection->client()->executeRaw([
            'XADD', $this->deadLetterStream, '*',
            'orig_stream', $this->sourceStream,
            'orig_id', '1-0',
            'reason', 'test failure',
            'attempts', '1',
            'payload_json', '{"event_id":',
            'failed_at', '2026-09-14T00:00:00+00:00',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('malformed payload');

        $this->service()->replay($deadLetterId, $this->sourceStream, 'operator');
    }

    public function test_legacy_dead_letter_replays_from_an_existing_original_source_entry(): void
    {
        $this->setUpReplayRedis();

        $sourceId = $this->redisConnection->client()->executeRaw([
            'XADD', $this->sourceStream, '*',
            'event_id', '42',
            'event_type', 'raw.sensor.received',
        ]);
        $deadLetterId = $this->redisConnection->client()->executeRaw([
            'XADD', $this->deadLetterStream, '*',
            'orig_stream', $this->sourceStream,
            'orig_id', $sourceId,
            'reason', 'legacy test failure',
            'attempts', '1',
            'failed_at', '2026-09-14T00:00:00+00:00',
        ]);

        $result = $this->service()->replay($deadLetterId, $this->sourceStream, 'operator');

        $this->assertSame('replayed', $result['status']);
        $this->assertSame([
            'event_id' => '42',
            'event_type' => 'raw.sensor.received',
        ], $this->streamFields($this->sourceStream, (string) $result['replay_id']));
    }

    private function service(): DeadLetterStreamService
    {
        return new DeadLetterStreamService(
            $this->redisConnection,
            $this->deadLetterStream,
            [$this->sourceStream, $this->otherSourceStream],
            $this->auditStream,
        );
    }

    /** @return array<string,string> */
    private function streamFields(string $stream, string $id): array
    {
        $entry = array_values($this->redisConnection->client()->executeRaw(['XRANGE', $stream, $id, $id]))[0] ?? [];
        $rawFields = array_is_list($entry) ? ($entry[1] ?? []) : $entry;
        $fields = [];

        foreach ($rawFields as $key => $value) {
            if (is_int($key)) {
                if ($key % 2 === 0) {
                    $fields[(string) $value] = (string) ($rawFields[$key + 1] ?? '');
                }
            } else {
                $fields[(string) $key] = (string) $value;
            }
        }

        return $fields;
    }
}
