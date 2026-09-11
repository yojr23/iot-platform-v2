<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DomainEventOutbox;
use App\Models\RawEventOutbox;
use App\Models\RawSensorEvent;
use App\Services\Ingestion\Cdc\CdcOutboxStreamConsumer;
use App\Services\Ingestion\DomainEventPublisher;
use App\Services\Ingestion\RawSensorEventPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\RedisManager;
use Tests\TestCase;

/**
 * Gate 10 (PLAN.md Stage 10, Task 5.7) crash/idempotency matrix for the CDC outbox publisher.
 *
 * Runs against a real Redis 7 server via predis (the raw() helper is client-agnostic, so this also
 * exercises Docker's phpredis path). If no Redis is reachable the whole test is skipped as
 * ENVIRONMENT_CONSTRAINT — it never fakes a pass. The two publishers are replaced by counting
 * fakes so we assert the publish contract without needing the downstream application streams.
 */
class CdcOutboxStreamConsumerTest extends TestCase
{
    use RefreshDatabase;

    private RedisManager $redis;
    private \Illuminate\Redis\Connections\Connection $conn;
    private string $rawStream = 'test.cdc.raw_event_outboxes';
    private string $domainStream = 'test.cdc.domain_event_outboxes';
    private string $group = 'outbox-publish-v1';
    private string $dlq = 'test.dead-letter-events';

    private CountingRawPublisher $rawPublisher;
    private CountingDomainPublisher $domainPublisher;

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

        foreach ([$this->rawStream, $this->domainStream, $this->dlq] as $s) {
            $this->conn->client()->executeRaw(['DEL', $s]);
        }

        $this->rawPublisher = new CountingRawPublisher;
        $this->domainPublisher = new CountingDomainPublisher;
    }

    private function consumer(): CdcOutboxStreamConsumer
    {
        return new CdcOutboxStreamConsumer(
            $this->conn,
            $this->rawPublisher,
            $this->domainPublisher,
            $this->rawStream,
            $this->domainStream,
            $this->group,
            $this->dlq,
        );
    }

    /** @param array<string,mixed> $after */
    private function cdcXadd(string $stream, string $op, array $after): void
    {
        $this->conn->client()->executeRaw([
            'XADD', $stream, '*',
            'key', json_encode(['id' => $after['id'] ?? null]),
            'value', json_encode(['op' => $op, 'before' => null, 'after' => $after, 'source' => ['db' => 'iot_platform']]),
        ]);
    }

    private function seedRawOutbox(string $status = 'pending'): RawEventOutbox
    {
        $device = Device::factory()->create(['serial_number' => 'SN-CDC']);
        $event = RawSensorEvent::create([
            'topic' => 'test', 'source' => 'test', 'source_event_id' => 'cdc-'.uniqid(),
            'node_id' => 'SN-CDC',
            'payload' => ['sensors' => ['temp' => ['value' => 21.0]]],
            'received_at' => now(), 'status' => 'received',
        ]);

        return RawEventOutbox::create(['raw_sensor_event_id' => $event->id, 'status' => $status]);
    }

    private function seedDomainOutbox(string $status = 'pending'): DomainEventOutbox
    {
        return DomainEventOutbox::create([
            'event_type' => 'alert.resolved',
            'aggregate_type' => 'alert',
            'aggregate_id' => '1',
            'payload' => ['foo' => 'bar'],
            'status' => $status,
        ]);
    }

    private function pendingCount(string $stream): int
    {
        $reply = $this->conn->client()->executeRaw(['XPENDING', $stream, $this->group]);

        return (int) ($reply[0] ?? 0);
    }

    // 1
    public function test_raw_pending_cdc_insert_publishes_exactly_once(): void
    {
        $outbox = $this->seedRawOutbox('pending');
        $this->cdcXadd($this->rawStream, 'c', ['id' => $outbox->id, 'status' => 'pending']);

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $this->rawPublisher->calls);
        $this->assertSame(0, $this->domainPublisher->calls);
        $this->assertSame(1, $stats['acked']);
        $this->assertSame('published', $outbox->fresh()->status);
    }

    // 2
    public function test_domain_pending_cdc_insert_publishes_exactly_once(): void
    {
        $outbox = $this->seedDomainOutbox('pending');
        $this->cdcXadd($this->domainStream, 'c', ['id' => $outbox->id, 'status' => 'pending']);

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $this->domainPublisher->calls);
        $this->assertSame(0, $this->rawPublisher->calls);
        $this->assertSame(1, $stats['acked']);
        $this->assertSame('published', $outbox->fresh()->status);
        // delivered_at stays owned by DomainEventBroadcastConsumer.
        $this->assertNull($outbox->fresh()->delivered_at);
    }

    // 3
    public function test_after_image_already_published_is_not_republished(): void
    {
        $outbox = $this->seedRawOutbox('published');
        $this->cdcXadd($this->rawStream, 'c', ['id' => $outbox->id, 'status' => 'published']);

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(0, $this->rawPublisher->calls);
        $this->assertSame(1, $stats['acked']);
    }

    // 4
    public function test_update_op_from_mark_published_is_ignored(): void
    {
        $outbox = $this->seedRawOutbox('published');
        $this->cdcXadd($this->rawStream, 'u', ['id' => $outbox->id, 'status' => 'published']);

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(0, $this->rawPublisher->calls);
        $this->assertSame(1, $stats['acked']);
    }

    // 5
    public function test_redelivery_republishes_but_outbox_ends_published_once(): void
    {
        $outbox = $this->seedRawOutbox('pending');
        // Physical duplicate CDC entry (at-least-once).
        $this->cdcXadd($this->rawStream, 'c', ['id' => $outbox->id, 'status' => 'pending']);
        $this->cdcXadd($this->rawStream, 'c', ['id' => $outbox->id, 'status' => 'pending']);

        $this->consumer()->runOnce('worker-A', 10, 100);

        // Physical redelivery is allowed (>=1 publish); the durable row is published exactly once.
        $this->assertGreaterThanOrEqual(1, $this->rawPublisher->calls);
        $this->assertSame('published', $outbox->fresh()->status);
    }

    // 6
    public function test_publish_failure_leaves_cdc_entry_pending(): void
    {
        $this->rawPublisher->result = false;
        $outbox = $this->seedRawOutbox('pending');
        $this->cdcXadd($this->rawStream, 'c', ['id' => $outbox->id, 'status' => 'pending']);

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $stats['pending']);
        $this->assertSame(0, $stats['acked']);
        $this->assertSame(1, $this->pendingCount($this->rawStream), 'entry must remain in the PEL for reclaim');
        $this->assertNotSame('published', $outbox->fresh()->status);
    }

    // 7
    public function test_terminal_failure_dead_letters_before_ack(): void
    {
        $this->rawPublisher->result = false;
        $outbox = $this->seedRawOutbox('pending');
        $this->cdcXadd($this->rawStream, 'c', ['id' => $outbox->id, 'status' => 'pending']);

        // Reclaim with idle 0 so each iteration re-delivers and increments the delivery count until
        // MAX_ATTEMPTS is exceeded and the entry is quarantined.
        $dlq = 0;
        for ($i = 0; $i < CdcOutboxStreamConsumer::MAX_ATTEMPTS + 1; $i++) {
            $stats = $this->consumer()->runOnce('worker-A', 10, 50, 0);
            $dlq += $stats['dlq'];
        }

        $this->assertSame(1, $dlq);
        $this->assertSame(1, (int) $this->conn->client()->executeRaw(['XLEN', $this->dlq]));
        $this->assertSame(0, $this->pendingCount($this->rawStream), 'DLQ entry must be acked afterwards');
    }

    // 8
    public function test_snapshot_read_op_publishes_old_pending_outbox(): void
    {
        $outbox = $this->seedRawOutbox('pending');
        $this->cdcXadd($this->rawStream, 'r', ['id' => $outbox->id, 'status' => 'pending']);

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $this->rawPublisher->calls);
        $this->assertSame(1, $stats['acked']);
        $this->assertSame('published', $outbox->fresh()->status);
    }

    // 9
    public function test_snapshot_read_op_ignores_already_published_outbox(): void
    {
        $outbox = $this->seedRawOutbox('published');
        $this->cdcXadd($this->rawStream, 'r', ['id' => $outbox->id, 'status' => 'published']);

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(0, $this->rawPublisher->calls);
        $this->assertSame(1, $stats['acked']);
    }

    // 10
    public function test_publishing_status_from_crashed_relay_is_republished(): void
    {
        // A row the old relay left in 'publishing' after a crash. after-image status is 'pending'
        // (or 'publishing'); either way it is NOT 'published', so it must be republished.
        $outbox = $this->seedRawOutbox('publishing');
        $this->cdcXadd($this->rawStream, 'r', ['id' => $outbox->id, 'status' => 'publishing']);

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $this->rawPublisher->calls);
        $this->assertSame(1, $stats['acked']);
        $this->assertSame('published', $outbox->fresh()->status);
    }
}

class CountingRawPublisher extends RawSensorEventPublisher
{
    public int $calls = 0;
    public bool $result = true;

    public function publish(RawSensorEvent $event): bool
    {
        $this->calls++;

        return $this->result;
    }
}

class CountingDomainPublisher extends DomainEventPublisher
{
    public int $calls = 0;
    public bool $result = true;

    public function publish(DomainEventOutbox $outbox): bool
    {
        $this->calls++;

        return $this->result;
    }
}
