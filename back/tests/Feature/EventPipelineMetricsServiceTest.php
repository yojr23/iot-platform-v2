<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Monitoring\EventPipelineMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

/**
 * Step 7.3 endpoint contract + Step 7.1 shape, following the same admin-gate assertions already
 * proven for `/api/internal/metrics/api-performance` in AdminAccessTest.
 *
 * These HTTP-level assertions do not require a live Redis: `EventPipelineMetricsService::snapshot()`
 * catches Redis failures per-metric and reports unknown measurements while preserving the HTTP 200
 * contract, so the endpoint remains usable in an environment without Redis.
 */
class EventPipelineMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    public function test_anonymous_request_is_unauthorized(): void
    {
        $this->getJson('/api/internal/metrics/event-pipeline')
            ->assertUnauthorized();
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->getJson('/api/internal/metrics/event-pipeline')
            ->assertForbidden();
    }

    public function test_admin_receives_documented_shape(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)
            ->getJson('/api/internal/metrics/event-pipeline')
            ->assertOk();

        $response->assertJsonStructure([
            'streams' => [
                'raw_events' => ['available', 'error', 'xlen', 'pending_count', 'lag', 'oldest_pending_age_ms'],
                'domain_events' => ['available', 'error', 'xlen', 'pending_count', 'lag', 'oldest_pending_age_ms'],
                'dead_letter' => ['available', 'error', 'xlen', 'pending_count', 'lag', 'oldest_pending_age_ms'],
            ],
            'consumers' => [
                'raw_process' => ['available', 'error', 'xlen', 'pending_count', 'lag', 'oldest_pending_age_ms'],
                'browser_delivery' => ['available', 'error', 'xlen', 'pending_count', 'lag', 'oldest_pending_age_ms'],
                'outbox_cdc' => ['available', 'error', 'xlen', 'pending_count', 'lag', 'oldest_pending_age_ms', 'stream_errors'],
            ],
            'outbox' => ['raw', 'domain'],
        ]);
    }

    public function test_snapshot_returns_top_level_keys_and_sub_keys(): void
    {
        $snapshot = (new EventPipelineMetricsService())->snapshot();

        $this->assertArrayHasKey('streams', $snapshot);
        $this->assertArrayHasKey('consumers', $snapshot);
        $this->assertArrayHasKey('outbox', $snapshot);

        $this->assertArrayHasKey('raw_events', $snapshot['streams']);
        $this->assertArrayHasKey('domain_events', $snapshot['streams']);
        $this->assertArrayHasKey('dead_letter', $snapshot['streams']);

        $this->assertArrayHasKey('raw_process', $snapshot['consumers']);
        $this->assertArrayHasKey('browser_delivery', $snapshot['consumers']);
        $this->assertArrayHasKey('outbox_cdc', $snapshot['consumers']);

        $this->assertArrayHasKey('raw', $snapshot['outbox']);
        $this->assertArrayHasKey('domain', $snapshot['outbox']);

        $this->assertArrayHasKey('pending', $snapshot['outbox']['raw']);
        $this->assertArrayHasKey('publish_latency_p50_ms', $snapshot['outbox']['raw']);
        $this->assertArrayHasKey('delivery_latency_p50_ms', $snapshot['outbox']['domain']);
    }

    public function test_redis_read_failures_are_exposed_as_unknown_pipeline_measurements(): void
    {
        $client = Mockery::mock(\Redis::class);
        $client->shouldReceive('rawCommand')->andThrow(new \RuntimeException('Redis unavailable'));

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('client')->andReturn($client);
        Redis::shouldReceive('connection')->with('default')->andReturn($connection);

        $snapshot = (new EventPipelineMetricsService())->snapshot();

        $this->assertFalse($snapshot['streams']['raw_events']['available']);
        $this->assertSame('redis_unavailable', $snapshot['streams']['raw_events']['error']);
        $this->assertNull($snapshot['streams']['raw_events']['xlen']);
        $this->assertNull($snapshot['consumers']['raw_process']['lag']);
    }

    public function test_partially_failed_stream_health_hides_all_numeric_measurements(): void
    {
        $rawStream = (string) config('app.ingestion_raw_events_stream', 'iot.raw-events');
        $rawGroup = (string) config('app.ingestion_raw_consumer_group', 'raw-process-v1');

        $client = Mockery::mock(\Redis::class);
        $client->shouldReceive('rawCommand')->andReturnUsing(function (...$command) use ($rawStream, $rawGroup) {
            return match ($command[0]) {
                'XLEN' => 7,
                'XINFO' => [['name', $rawGroup, 'pending', 2, 'lag', 5]],
                'XPENDING' => $command[1] === $rawStream
                    ? throw new \RuntimeException('Redis unavailable while reading pending entries')
                    : [['1-0', 'worker-1', 23, 1]],
            };
        });

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('client')->andReturn($client);
        Redis::shouldReceive('connection')->with('default')->andReturn($connection);

        $snapshot = (new EventPipelineMetricsService())->snapshot();
        $health = $snapshot['streams']['raw_events'];

        $this->assertFalse($health['available']);
        $this->assertSame('redis_unavailable', $health['error']);
        $this->assertNull($health['xlen']);
        $this->assertNull($health['pending_count']);
        $this->assertNull($health['lag']);
        $this->assertNull($health['oldest_pending_age_ms']);
        $this->assertNull($health['dlq_length']);
    }

    public function test_successful_redis_measurements_are_available_and_numeric(): void
    {
        $client = Mockery::mock(\Redis::class);
        $client->shouldReceive('rawCommand')->andReturnUsing(function (...$command) {
            return match ($command[0]) {
                'XLEN' => 7,
                'XINFO' => [['name', $command[2] === config('app.ingestion_raw_events_stream', 'iot.raw-events') ? config('app.ingestion_raw_consumer_group', 'raw-process-v1') : config('app.domain_events_consumer_group', 'browser-delivery-v1'), 'pending', 2, 'lag', 5]],
                'XPENDING' => [['1-0', 'worker-1', 23, 1]],
            };
        });

        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('client')->andReturn($client);
        Redis::shouldReceive('connection')->with('default')->andReturn($connection);

        $snapshot = (new EventPipelineMetricsService())->snapshot();

        $this->assertTrue($snapshot['streams']['raw_events']['available']);
        $this->assertSame(7, $snapshot['streams']['raw_events']['xlen']);
        $this->assertSame(5, $snapshot['consumers']['raw_process']['lag']);
        $this->assertSame(23, $snapshot['consumers']['raw_process']['oldest_pending_age_ms']);
    }

    public function test_cdc_health_sums_each_configured_stream_measurement(): void
    {
        $rawStream = 'metrics-test:cdc-raw';
        $domainStream = 'metrics-test:cdc-domain';
        $group = 'metrics-test:cdc-group';
        $original = [
            'app.cdc_raw_outbox_stream' => config('app.cdc_raw_outbox_stream'),
            'app.cdc_domain_outbox_stream' => config('app.cdc_domain_outbox_stream'),
            'app.cdc_outbox_consumer_group' => config('app.cdc_outbox_consumer_group'),
        ];

        config([
            'app.cdc_raw_outbox_stream' => $rawStream,
            'app.cdc_domain_outbox_stream' => $domainStream,
            'app.cdc_outbox_consumer_group' => $group,
        ]);

        try {
            $client = Mockery::mock(\Redis::class);
            $client->shouldReceive('rawCommand')->andReturnUsing(function (...$command) use ($rawStream, $domainStream, $group) {
                $stream = $command[0] === 'XINFO' ? ($command[2] ?? null) : ($command[1] ?? null);

                return match ($command[0]) {
                    'XLEN' => $stream === $rawStream ? 3 : ($stream === $domainStream ? 5 : 0),
                    'XINFO' => [['name', $stream === $rawStream || $stream === $domainStream ? $group : 'other-group', 'pending', $stream === $rawStream ? 2 : 4, 'lag', $stream === $rawStream ? 6 : 8]],
                    'XPENDING' => [['1-0', 'worker-1', $stream === $rawStream ? 12 : 30, 1]],
                };
            });

            $connection = Mockery::mock(Connection::class);
            $connection->shouldReceive('client')->andReturn($client);
            Redis::shouldReceive('connection')->with('default')->andReturn($connection);

            $health = (new EventPipelineMetricsService())->snapshot()['consumers']['outbox_cdc'];

            $this->assertTrue($health['available']);
            $this->assertSame(8, $health['xlen']);
            $this->assertSame(6, $health['pending_count']);
            $this->assertSame(14, $health['lag']);
            $this->assertSame(30, $health['oldest_pending_age_ms']);
            $this->assertSame([], $health['stream_errors']);
        } finally {
            config($original);
        }
    }

    public function test_cdc_health_reports_the_unavailable_source_stream_and_hides_aggregate_numbers(): void
    {
        $rawStream = 'metrics-test:cdc-raw';
        $domainStream = 'metrics-test:cdc-domain';
        $group = 'metrics-test:cdc-group';
        $original = [
            'app.cdc_raw_outbox_stream' => config('app.cdc_raw_outbox_stream'),
            'app.cdc_domain_outbox_stream' => config('app.cdc_domain_outbox_stream'),
            'app.cdc_outbox_consumer_group' => config('app.cdc_outbox_consumer_group'),
        ];

        config([
            'app.cdc_raw_outbox_stream' => $rawStream,
            'app.cdc_domain_outbox_stream' => $domainStream,
            'app.cdc_outbox_consumer_group' => $group,
        ]);

        try {
            $client = Mockery::mock(\Redis::class);
            $client->shouldReceive('rawCommand')->andReturnUsing(function (...$command) use ($domainStream, $group) {
                $stream = $command[0] === 'XINFO' ? ($command[2] ?? null) : ($command[1] ?? null);

                if ($command[0] === 'XINFO' && $stream === $domainStream) {
                    throw new \RuntimeException('Redis unavailable for the domain CDC stream');
                }

                return match ($command[0]) {
                    'XLEN' => 4,
                    'XINFO' => [['name', $group, 'pending', 1, 'lag', 2]],
                    'XPENDING' => [['1-0', 'worker-1', 20, 1]],
                };
            });

            $connection = Mockery::mock(Connection::class);
            $connection->shouldReceive('client')->andReturn($client);
            Redis::shouldReceive('connection')->with('default')->andReturn($connection);

            $health = (new EventPipelineMetricsService())->snapshot()['consumers']['outbox_cdc'];

            $this->assertFalse($health['available']);
            $this->assertSame('redis_unavailable', $health['error']);
            $this->assertSame([$domainStream => 'redis_unavailable'], $health['stream_errors']);
            $this->assertNull($health['xlen']);
            $this->assertNull($health['pending_count']);
            $this->assertNull($health['lag']);
            $this->assertNull($health['oldest_pending_age_ms']);
        } finally {
            config($original);
        }
    }

    public function test_cdc_health_with_no_configured_streams_is_unknown(): void
    {
        $original = [
            'app.cdc_raw_outbox_stream' => config('app.cdc_raw_outbox_stream'),
            'app.cdc_domain_outbox_stream' => config('app.cdc_domain_outbox_stream'),
        ];

        config([
            'app.cdc_raw_outbox_stream' => '',
            'app.cdc_domain_outbox_stream' => '',
        ]);

        try {
            $health = (new EventPipelineMetricsService())->snapshot()['consumers']['outbox_cdc'];

            $this->assertFalse($health['available']);
            $this->assertSame('cdc_streams_unconfigured', $health['error']);
            $this->assertSame([], $health['stream_errors']);
            $this->assertNull($health['xlen']);
            $this->assertNull($health['pending_count']);
            $this->assertNull($health['lag']);
            $this->assertNull($health['oldest_pending_age_ms']);
        } finally {
            config($original);
        }
    }

    public function test_real_redis_stream_measurements_are_available_and_numeric_when_redis_is_configured(): void
    {
        try {
            Redis::connection('default')->ping();
        } catch (\Throwable) {
            $this->markTestSkipped('A real Redis server is not configured for this test run.');
        }

        $suffix = bin2hex(random_bytes(8));
        $stream = "metrics-test:raw:{$suffix}";
        $group = "metrics-test:group:{$suffix}";
        $original = [
            'app.ingestion_raw_events_stream' => config('app.ingestion_raw_events_stream'),
            'app.ingestion_raw_consumer_group' => config('app.ingestion_raw_consumer_group'),
        ];

        config([
            'app.ingestion_raw_events_stream' => $stream,
            'app.ingestion_raw_consumer_group' => $group,
        ]);

        try {
            Redis::command('xadd', [$stream, '*', 'event_type', 'test']);
            Redis::command('xgroup', ['CREATE', $stream, $group, '0']);

            $snapshot = (new EventPipelineMetricsService())->snapshot();

            $this->assertTrue($snapshot['streams']['raw_events']['available']);
            $this->assertIsInt($snapshot['streams']['raw_events']['xlen']);
            $this->assertIsInt($snapshot['consumers']['raw_process']['lag']);
        } finally {
            Redis::command('del', [$stream]);
            config($original);
        }
    }

    public function test_counters_only_accept_the_documented_names(): void
    {
        EventPipelineMetricsService::increment('raw_processed');
        EventPipelineMetricsService::increment('not_a_real_counter');

        $snapshot = (new EventPipelineMetricsService())->snapshot();

        $this->assertSame(1, $snapshot['consumers']['raw_process']['raw_processed']);
    }
}
