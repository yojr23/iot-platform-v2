<?php

namespace Tests\Feature;

use App\Events\AlertResolved;
use App\Events\DeviceStatusUpdated;
use App\Events\NewSensorReading;
use App\Models\Alert;
use App\Models\Device;
use App\Models\DomainEventOutbox;
use App\Models\SensorReading;
use App\Services\Ingestion\DomainEventBroadcastConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Stage 4.1/4.3/4.4 crash-matrix coverage for the `browser-delivery-v1` domain-event consumer,
 * mirroring `tests/Feature/RawStreamConsumerTest.php` (Stage 3) against the same real Redis 7
 * server. Skipped as ENVIRONMENT_CONSTRAINT (never faked) if Redis is unreachable.
 */
class DomainEventBroadcastConsumerTest extends TestCase
{
    use RefreshDatabase;

    private RedisManager $redis;
    private \Illuminate\Redis\Connections\Connection $conn;
    private string $stream = 'test.domain-events';
    private string $group = 'browser-delivery-v1';
    private string $dlq = 'test.dead-letter-events';

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

    private function xadd(int $outboxId, string $eventType = 'alert.resolved'): void
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
            'payload' => ['alert_id' => $alert->id],
            'status' => 'published',
        ]);
    }

    private function deviceStatusOutbox(): DomainEventOutbox
    {
        $device = Device::factory()->create(['status' => false, 'is_active' => false]);

        return DomainEventOutbox::factory()->create([
            'event_type' => 'device.status.changed',
            'aggregate_type' => 'device',
            'aggregate_id' => (string) $device->id,
            'payload' => ['device_id' => $device->id],
            'status' => 'published',
        ]);
    }

    private function sensorReadingOutbox(): DomainEventOutbox
    {
        $reading = SensorReading::factory()->create();

        return DomainEventOutbox::factory()->create([
            'event_type' => 'sensor.reading.created',
            'aggregate_type' => 'sensor_reading',
            'aggregate_id' => (string) $reading->id,
            'payload' => ['reading_id' => $reading->id],
            'status' => 'published',
        ]);
    }

    public function test_alert_resolved_fact_is_broadcast_exactly_once(): void
    {
        Event::fake([AlertResolved::class]);

        $outbox = $this->resolvedAlertOutbox();
        $this->xadd($outbox->id, 'alert.resolved');

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $stats['acked']);
        Event::assertDispatchedTimes(AlertResolved::class, 1);
        $this->assertNotNull($outbox->fresh()->delivered_at);
    }

    public function test_device_status_changed_fact_is_broadcast_exactly_once(): void
    {
        Event::fake([DeviceStatusUpdated::class]);

        $outbox = $this->deviceStatusOutbox();
        $this->xadd($outbox->id, 'device.status.changed');

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $stats['acked']);
        Event::assertDispatchedTimes(DeviceStatusUpdated::class, 1);
    }

    public function test_sensor_reading_created_fact_is_broadcast_exactly_once(): void
    {
        Event::fake([NewSensorReading::class]);

        $outbox = $this->sensorReadingOutbox();
        $this->xadd($outbox->id, 'sensor.reading.created');

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $stats['acked']);
        Event::assertDispatchedTimes(NewSensorReading::class, 1);
        $this->assertNotNull($outbox->fresh()->delivered_at);
    }

    public function test_duplicate_sensor_reading_delivery_is_not_rebroadcast(): void
    {
        Event::fake([NewSensorReading::class]);

        $outbox = $this->sensorReadingOutbox();
        $this->xadd($outbox->id, 'sensor.reading.created');
        $this->xadd($outbox->id, 'sensor.reading.created');

        $this->consumer()->runOnce('worker-A', 10, 100);

        Event::assertDispatchedTimes(NewSensorReading::class, 1);
    }

    public function test_duplicate_delivery_of_the_same_fact_broadcasts_only_once(): void
    {
        Event::fake([AlertResolved::class]);

        $outbox = $this->resolvedAlertOutbox();
        // Same outbox row delivered twice (at-least-once relay, exactly like the raw pipeline).
        $this->xadd($outbox->id, 'alert.resolved');
        $this->xadd($outbox->id, 'alert.resolved');

        $this->consumer()->runOnce('worker-A', 10, 100);

        Event::assertDispatchedTimes(AlertResolved::class, 1);
    }

    public function test_crash_after_broadcast_before_ack_is_recovered_without_double_broadcast(): void
    {
        Event::fake([AlertResolved::class]);

        $outbox = $this->resolvedAlertOutbox();
        $this->xadd($outbox->id, 'alert.resolved');

        // Simulate a worker reading the message into its PEL and crashing before XACK.
        $this->conn->client()->executeRaw(['XREADGROUP', 'GROUP', $this->group, 'worker-dead', 'COUNT', 10, 'STREAMS', $this->stream, '>']);

        // Recovery worker reclaims idle-pending (idle 0) and processes.
        $stats = $this->consumer()->runOnce('worker-B', 10, 100, 0);

        $this->assertSame(1, $stats['acked']);
        Event::assertDispatchedTimes(AlertResolved::class, 1);

        $pending = $this->conn->client()->executeRaw(['XPENDING', $this->stream, $this->group]);
        $this->assertSame(0, (int) $pending[0]);
    }

    public function test_unknown_outbox_id_goes_to_dlq(): void
    {
        $this->xadd(999999, 'alert.resolved');

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $stats['dlq']);
        $this->assertSame(1, (int) $this->conn->client()->executeRaw(['XLEN', $this->dlq]));
    }

    public function test_already_delivered_outbox_row_is_skipped_without_rebroadcast(): void
    {
        Event::fake([AlertResolved::class]);

        $outbox = $this->resolvedAlertOutbox();
        $outbox->forceFill(['delivered_at' => now()])->save();
        $this->xadd($outbox->id, 'alert.resolved');

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $stats['acked']);
        Event::assertNotDispatched(AlertResolved::class);
    }
}
