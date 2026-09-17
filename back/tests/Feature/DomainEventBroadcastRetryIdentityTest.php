<?php

namespace Tests\Feature;

use App\Events\AlertResolved;
use App\Models\Alert;
use App\Models\DomainEventOutbox;
use App\Services\Ingestion\DomainEventBroadcastConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * PENDING MAC VERIFICATION — written on Windows, never executed here.
 *
 * Fix 3 (persistent event-envelope identity on retry). Covers PLAN.md/task item 5 and 6: a
 * retried broadcast of the SAME domain fact must keep the SAME event_id and the SAME occurred_at
 * across delivery attempts — never regenerate either on redelivery.
 *
 * Root cause this guards: `App\Events\Concerns\HasEventEnvelope` lazily generates `event_id`
 * (Str::uuid()) and `occurred_at` (now()) with `??=` on the event OBJECT instance.
 * `DomainEventBroadcastConsumer::broadcastFact()` builds a BRAND NEW event object on every
 * delivery attempt (that is the only live dispatch site for these events — see
 * NotificationService::broadcastNewAlert(), which is documented dead code and never wired). Before
 * the fix, two delivery attempts for the same `DomainEventOutbox` row therefore produced two
 * different envelope identities for what is logically the same fact — breaking any downstream
 * dedup keyed by event_id. The fix seeds the envelope from the outbox row's own stable identity
 * (`id`, `created_at`) via `HasEventEnvelope::seedEnvelope()`, mirroring what
 * `RawSensorEventPublisher`/`DomainEventPublisher`/`CdcOutboxStreamConsumer` already did correctly
 * for the outbox-to-stream leg.
 *
 * Mirrors the existing Redis harness in `DomainEventBroadcastConsumerTest` (same real Redis 7
 * server, skipped as ENVIRONMENT_CONSTRAINT — never faked — if unreachable).
 */
class DomainEventBroadcastRetryIdentityTest extends TestCase
{
    use RefreshDatabase;

    private RedisManager $redis;
    private \Illuminate\Redis\Connections\Connection $conn;
    private string $stream = 'test.domain-events.retry-identity';
    private string $group = 'browser-delivery-v1';
    private string $dlq = 'test.dead-letter-events.retry-identity';

    protected function setUp(): void
    {
        parent::setUp();

        $host = env('TEST_REDIS_HOST', '127.0.0.1');
        $port = (int) env('TEST_REDIS_PORT', 6399);

        $this->redis = new RedisManager(app(), 'predis', [
            'client' => 'predis',
            'default' => ['host' => $host, 'port' => $port, 'database' => 15],
        ]);

        try {
            $this->conn = $this->redis->connection('default');
            $this->conn->client()->executeRaw(['PING']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('ENVIRONMENT_CONSTRAINT: no Redis at '.$host.':'.$port.' ('.$e->getMessage().')');
        }

        foreach ([$this->stream, $this->dlq] as $s) {
            $this->conn->client()->executeRaw(['DEL', $s]);
        }
    }

    private function consumer(): DomainEventBroadcastConsumer
    {
        return new DomainEventBroadcastConsumer($this->conn, $this->stream, $this->group, $this->dlq);
    }

    private function xadd(int $outboxId, string $eventType): void
    {
        $this->conn->client()->executeRaw([
            'XADD', $this->stream, '*',
            'event_id', (string) $outboxId,
            'event_type', $eventType, 'event_version', '1',
        ]);
    }

    private function resolvedAlertOutbox(): DomainEventOutbox
    {
        $alert = Alert::factory()->create(['resolved' => true, 'resolved_at' => now()]);

        return DomainEventOutbox::factory()->create([
            'event_type' => 'alert.resolved',
            'aggregate_type' => 'alert',
            'aggregate_id' => (string) $alert->id,
            'payload' => [
                'alert_id' => $alert->id,
                'resolved' => true,
                'resolved_at' => $alert->resolved_at?->toIso8601String(),
            ],
            'status' => 'published',
        ]);
    }

    /**
     * Simulates the class's own documented "accepted at-least-once duplicate window" (see
     * `DomainEventBroadcastConsumer` class docblock: "Ordering matters: XADD already succeeded.
     * Commit the outbox status, then ack. A crash between these two is the accepted at-least-once
     * duplicate window"): first delivery broadcasts and durably marks delivered_at, but we then
     * force the row back to "not yet delivered" (standing in for "the crash meant delivered_at
     * never actually committed") and redeliver the same outbox id on a fresh stream entry — a
     * second, independent `broadcastFact()` call, exactly like a real redelivery.
     */
    private function broadcastTwiceForSameOutbox(DomainEventOutbox $outbox): array
    {
        Event::fake([AlertResolved::class]);

        $this->xadd($outbox->id, 'alert.resolved');
        $this->consumer()->runOnce('worker-A', 10, 100);

        $first = null;
        Event::assertDispatched(AlertResolved::class, function (AlertResolved $event) use (&$first) {
            $first = $event;

            return true;
        });

        // Force the row back to "undelivered" — the crash-window stand-in described above — then
        // redeliver on a fresh stream entry so handleMessage() runs broadcastFact() again for the
        // SAME outbox row.
        $outbox->forceFill(['delivered_at' => null])->save();
        Event::fake([AlertResolved::class]);
        $this->xadd($outbox->id, 'alert.resolved');
        $this->consumer()->runOnce('worker-A', 10, 100);

        $second = null;
        Event::assertDispatched(AlertResolved::class, function (AlertResolved $event) use (&$second) {
            $second = $event;

            return true;
        });

        return [$first, $second];
    }

    public function test_retried_broadcast_preserves_the_same_event_id(): void
    {
        $outbox = $this->resolvedAlertOutbox();

        [$first, $second] = $this->broadcastTwiceForSameOutbox($outbox);

        $this->assertNotSame('', $first->envelopeEventId());
        $this->assertSame(
            $first->envelopeEventId(),
            $second->envelopeEventId(),
            'a retried broadcast of the same outbox row must keep the same event_id'
        );
        // The stable identity is the outbox row's own id, not a fresh UUID per attempt.
        $this->assertSame((string) $outbox->id, $first->envelopeEventId());
    }

    public function test_retried_broadcast_preserves_occurred_at_unchanged(): void
    {
        $outbox = $this->resolvedAlertOutbox();

        [$first, $second] = $this->broadcastTwiceForSameOutbox($outbox);

        $this->assertNotSame('', $first->envelopeOccurredAt());
        $this->assertSame(
            $first->envelopeOccurredAt(),
            $second->envelopeOccurredAt(),
            'a retried broadcast of the same outbox row must keep the same occurred_at'
        );
        // The stable timestamp is the outbox row's own created_at, not now() at broadcast time.
        $this->assertSame($outbox->created_at->toIso8601String(), $first->envelopeOccurredAt());
    }
}
