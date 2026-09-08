<?php

namespace Tests\Unit;

use App\Models\DomainEventOutbox;
use App\Services\Ingestion\DomainEventPublisher;
use App\Services\Ingestion\DomainOutboxRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * PLAN.md Stage 4.1 relay crash-matrix, mirroring the Stage 3 `RawOutboxRelay` behaviour it copies:
 * a pending row is published exactly once when the publisher succeeds; a publish failure leaves the
 * row retryable (`pending`, `attempts` incremented) rather than stranding or silently dropping it;
 * an already-published row is never reclaimed/republished by a later sweep.
 */
class DomainOutboxRelayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_pending_row_is_published_exactly_once(): void
    {
        $outbox = DomainEventOutbox::factory()->create(['status' => 'pending']);

        $publisher = Mockery::mock(DomainEventPublisher::class);
        $publisher->shouldReceive('publish')->once()->andReturn(true);

        $stats = (new DomainOutboxRelay($publisher))->relayPending();

        $this->assertSame(['claimed' => 1, 'published' => 1, 'failed' => 0], $stats);
        $outbox->refresh();
        $this->assertSame('published', $outbox->status);
        $this->assertNotNull($outbox->published_at);
        $this->assertNull($outbox->locked_until);
    }

    public function test_publish_failure_leaves_row_pending_and_retryable_not_stranded(): void
    {
        $outbox = DomainEventOutbox::factory()->create(['status' => 'pending', 'attempts' => 0]);

        $publisher = Mockery::mock(DomainEventPublisher::class);
        $publisher->shouldReceive('publish')->once()->andReturn(false);

        $stats = (new DomainOutboxRelay($publisher))->relayPending();

        $this->assertSame(['claimed' => 1, 'published' => 0, 'failed' => 1], $stats);
        $outbox->refresh();
        $this->assertSame('pending', $outbox->status);
        $this->assertSame(1, $outbox->attempts);
        $this->assertNull($outbox->locked_until);
    }

    public function test_already_published_row_is_not_reclaimed_by_a_later_sweep(): void
    {
        DomainEventOutbox::factory()->create([
            'status' => 'published',
            'published_at' => now(),
        ]);

        $publisher = Mockery::mock(DomainEventPublisher::class);
        $publisher->shouldNotReceive('publish');

        $stats = (new DomainOutboxRelay($publisher))->relayPending();

        $this->assertSame(['claimed' => 0, 'published' => 0, 'failed' => 0], $stats);
    }

    public function test_lease_expired_publishing_row_is_reclaimed_and_republished(): void
    {
        $outbox = DomainEventOutbox::factory()->create([
            'status' => 'publishing',
            'locked_until' => now()->subMinute(),
        ]);

        $publisher = Mockery::mock(DomainEventPublisher::class);
        $publisher->shouldReceive('publish')->once()->andReturn(true);

        $stats = (new DomainOutboxRelay($publisher))->relayPending();

        $this->assertSame(1, $stats['claimed']);
        $this->assertSame('published', $outbox->fresh()->status);
    }
}
