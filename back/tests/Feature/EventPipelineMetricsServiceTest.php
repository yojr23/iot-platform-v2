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
 * catches Redis failures per-metric and degrades to zeroed defaults (same "never break the caller"
 * contract as `RawSensorEventPublisher::publish()`), so the endpoint still returns 200 with the
 * documented shape in an environment without Redis.
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
                'outbox_cdc' => ['available', 'error', 'xlen', 'pending_count', 'lag', 'oldest_pending_age_ms'],
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

    public function test_real_redis_stream_measurements_are_available_and_numeric_when_redis_is_configured(): void
    {
        try {
            Redis::connection('default')->ping();
        } catch (\Throwable) {
            $this->markTestSkipped('A real Redis server is not configured for this test run.');
        }

        $stream = (string) config('app.ingestion_raw_events_stream', 'iot.raw-events');
        $group = (string) config('app.ingestion_raw_consumer_group', 'raw-process-v1');
        Redis::command('xadd', [$stream, '*', 'event_type', 'test']);
        Redis::command('xgroup', ['CREATE', $stream, $group, '0']);

        $snapshot = (new EventPipelineMetricsService())->snapshot();

        $this->assertTrue($snapshot['streams']['raw_events']['available']);
        $this->assertIsInt($snapshot['streams']['raw_events']['xlen']);
        $this->assertIsInt($snapshot['consumers']['raw_process']['lag']);
    }

    public function test_counters_only_accept_the_documented_names(): void
    {
        EventPipelineMetricsService::increment('raw_processed');
        EventPipelineMetricsService::increment('not_a_real_counter');

        $snapshot = (new EventPipelineMetricsService())->snapshot();

        $this->assertSame(1, $snapshot['consumers']['raw_process']['raw_processed']);
    }
}
