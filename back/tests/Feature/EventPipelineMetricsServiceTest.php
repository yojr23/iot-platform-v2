<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Monitoring\EventPipelineMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'streams' => ['raw_events', 'domain_events', 'dead_letter'],
            'consumers' => ['raw_process', 'browser_delivery', 'outbox_cdc'],
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

    public function test_counters_only_accept_the_documented_names(): void
    {
        EventPipelineMetricsService::increment('raw_processed');
        EventPipelineMetricsService::increment('not_a_real_counter');

        $snapshot = (new EventPipelineMetricsService())->snapshot();

        $this->assertSame(1, $snapshot['consumers']['raw_process']['raw_processed']);
    }
}
