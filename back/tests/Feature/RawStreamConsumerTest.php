<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\RawSensorEvent;
use App\Models\Sensor;
use App\Services\Ingestion\RawReadingNormalizer;
use App\Services\Ingestion\SensorReadingService;
use App\Services\Ingestion\RawStreamConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\RedisManager;
use Tests\TestCase;

/**
 * Stage 3.3 crash-matrix coverage for the raw consumer, run against a real Redis 7 server via
 * predis (the raw() helper is client-agnostic, so this also exercises the code path Docker's
 * phpredis takes). If no Redis is reachable the whole test is skipped as ENVIRONMENT_CONSTRAINT —
 * it never fakes a pass.
 */
class RawStreamConsumerTest extends TestCase
{
    use RefreshDatabase;

    private RedisManager $redis;
    private \Illuminate\Redis\Connections\Connection $conn;
    private string $stream = 'test.raw-events';
    private string $group = 'raw-process-v1';
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

        // Clean slate for each test.
        foreach ([$this->stream, $this->dlq] as $s) {
            $this->conn->client()->executeRaw(['DEL', $s]);
        }
    }

    private function consumer(): RawStreamConsumer
    {
        return new RawStreamConsumer(
            $this->conn,
            new RawReadingNormalizer(app(SensorReadingService::class)),
            $this->stream,
            $this->group,
            $this->dlq,
        );
    }

    private function seedDeviceSensor(string $serial = 'SN-TEST', string $sensorName = 'temp'): Sensor
    {
        $device = Device::factory()->create(['serial_number' => $serial]);

        return Sensor::factory()->create(['device_id' => $device->id, 'name' => $sensorName]);
    }

    private function xadd(int $eventId): void
    {
        $this->conn->client()->executeRaw([
            'XADD', $this->stream, '*',
            'event_id', (string) $eventId,
            'event_type', 'raw.sensor.received', 'event_version', '1',
        ]);
    }

    private function rawEvent(string $serial, string $sensorName, $value, string $srcId): RawSensorEvent
    {
        return RawSensorEvent::create([
            'topic' => 'test', 'source' => 'test', 'source_event_id' => $srcId,
            'node_id' => $serial,
            'payload' => ['sensors' => [$sensorName => ['value' => $value]], 'timestamp' => now()->toIso8601String()],
            'received_at' => now(), 'status' => 'received',
        ]);
    }

    public function test_events_added_before_group_exists_are_still_processed(): void
    {
        $sensor = $this->seedDeviceSensor();
        $event = $this->rawEvent('SN-TEST', 'temp', 42.0, 'src-1');
        // XADD BEFORE the consumer/group is ever created — the P1 "XGROUP $ backlog loss" case.
        $this->xadd($event->id);

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $stats['acked']);
        $this->assertSame('processed', $event->fresh()->status);
        $this->assertSame(1, $sensor->readings()->count());
        $this->assertDatabaseCount('domain_event_outboxes', 1);
        $this->assertDatabaseHas('domain_event_outboxes', [
            'event_type' => 'sensor.reading.created',
        ]);
    }

    public function test_duplicate_delivery_yields_one_reading(): void
    {
        $sensor = $this->seedDeviceSensor();
        $event = $this->rawEvent('SN-TEST', 'temp', 21.0, 'src-2');
        // Same receipt delivered twice (at-least-once relay).
        $this->xadd($event->id);
        $this->xadd($event->id);

        $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $sensor->readings()->count(), 'duplicate delivery must not double-create readings');
        $this->assertSame('processed', $event->fresh()->status);
        $this->assertDatabaseCount('domain_event_outboxes', 1);
    }

    public function test_crash_after_apply_before_ack_is_recovered_without_duplicate(): void
    {
        $sensor = $this->seedDeviceSensor();
        $event = $this->rawEvent('SN-TEST', 'temp', 30.0, 'src-3');
        $this->xadd($event->id);

        // First worker reads (delivered to PEL) but "crashes" before ack: simulate by reading the
        // group directly and never acking.
        $this->conn->client()->executeRaw(['XREADGROUP', 'GROUP', $this->group, 'worker-dead', 'COUNT', 10, 'STREAMS', $this->stream, '>']);

        // Recovery worker reclaims idle-pending (idle 0) and processes.
        $stats = $this->consumer()->runOnce('worker-B', 10, 100, 0);

        $this->assertSame(1, $stats['acked']);
        $this->assertSame(1, $sensor->readings()->count());
        // PEL now empty.
        $pending = $this->conn->client()->executeRaw(['XPENDING', $this->stream, $this->group]);
        $this->assertSame(0, (int) $pending[0]);
    }

    public function test_unknown_event_id_goes_to_dlq(): void
    {
        $this->xadd(999999); // no such raw_sensor_event

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $stats['dlq']);
        $this->assertSame(1, (int) $this->conn->client()->executeRaw(['XLEN', $this->dlq]));
    }

    public function test_total_normalization_failure_is_not_marked_processed(): void
    {
        // Device exists, but the payload's sensor key maps to no sensor -> created=0, skipped=1.
        Device::factory()->create(['serial_number' => 'SN-NOSENSOR']);
        $event = $this->rawEvent('SN-NOSENSOR', 'nonexistent', 5.0, 'src-4');
        $this->xadd($event->id);

        $stats = $this->consumer()->runOnce('worker-A', 10, 100, 0);

        // created==0 with non-empty payload must NOT be acked as success — left pending for retry.
        $this->assertSame(0, $stats['acked']);
        $this->assertSame(1, $stats['pending']);
        $this->assertNotSame('processed', $event->fresh()->status);
    }
}
