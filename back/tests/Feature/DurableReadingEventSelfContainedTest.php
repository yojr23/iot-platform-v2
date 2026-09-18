<?php

namespace Tests\Feature;

use App\Events\NewSensorReading;
use App\Models\Device;
use App\Models\DomainEventOutbox;
use App\Models\Lab;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SensorType;
use App\Services\Ingestion\DomainEventBroadcastConsumer;
use App\Services\Ingestion\SensorReadingService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P1 (durable self-contained event): the sensor.reading.created outbox payload must carry every
 * immutable fact the broadcast needs (denormalized sensor/device/lab + the event-time audience
 * decision), so DomainEventBroadcastConsumer can deliver the fact even if the SensorReading row is
 * gone by delivery time — no silent loss, no re-read dependency.
 */
class DurableReadingEventSelfContainedTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_payload_is_self_contained_at_write_time(): void
    {
        $lab = Lab::factory()->create(['name' => 'Lab One']);
        $device = Device::factory()->create(['name' => 'Node One', 'lab_id' => $lab->id, 'status' => true, 'is_active' => true]);
        $type = SensorType::factory()->create(['name' => 'Temperature', 'unit' => '°C']);
        $sensor = Sensor::factory()->create([
            'name' => 'Ambient',
            'device_id' => $device->id,
            'sensor_type_id' => $type->id,
            'public_monitoring_enabled' => true,
        ]);

        $reading = app(SensorReadingService::class)->createReading($sensor, 24.4);

        $outbox = DomainEventOutbox::query()
            ->where('event_type', 'sensor.reading.created')
            ->where('aggregate_id', (string) $reading->id)
            ->firstOrFail();

        $p = $outbox->payload;
        $this->assertSame($reading->id, $p['reading_id']);
        $this->assertSame($sensor->id, $p['sensor_id']);
        $this->assertSame('Ambient', $p['sensor_name']);
        $this->assertSame('Temperature', $p['sensor_type']);
        $this->assertSame('°C', $p['unit']);
        $this->assertSame($device->id, $p['device_id']);
        $this->assertSame('Node One', $p['device_name']);
        $this->assertSame($lab->id, $p['lab_id']);
        $this->assertSame('Lab One', $p['lab_name']);
        // Event-time audience captured (sensor is public).
        $this->assertTrue($p['public_at_occurrence']);
    }

    public function test_private_sensor_records_event_time_audience_false(): void
    {
        $sensor = Sensor::factory()->create(['public_monitoring_enabled' => false]);
        $reading = app(SensorReadingService::class)->createReading($sensor, 10.0);

        $outbox = DomainEventOutbox::query()
            ->where('event_type', 'sensor.reading.created')
            ->where('aggregate_id', (string) $reading->id)
            ->firstOrFail();

        $this->assertFalse($outbox->payload['public_at_occurrence']);
    }

    /**
     * The consumer must still broadcast the fact after the SensorReading row is deleted — rebuilt
     * entirely from the enriched payload. Requires real Redis (skipped as ENVIRONMENT_CONSTRAINT
     * otherwise), mirroring DomainEventBroadcastConsumerTest.
     */
    public function test_reading_created_is_broadcast_after_reading_row_deleted(): void
    {
        $host = env('TEST_REDIS_HOST', '127.0.0.1');
        $port = (int) env('TEST_REDIS_PORT', 6399);
        $redis = new RedisManager(app(), 'predis', [
            'client' => 'predis',
            'default' => ['host' => $host, 'port' => $port, 'database' => 15],
        ]);
        try {
            $conn = $redis->connection('default');
            $conn->client()->executeRaw(['PING']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('ENVIRONMENT_CONSTRAINT: no Redis at '.$host.':'.$port);
        }

        $stream = 'test.durable-domain-events';
        $group = 'browser-delivery-v1';
        $conn->client()->executeRaw(['DEL', $stream]);
        try {
            $conn->client()->executeRaw(['XGROUP', 'CREATE', $stream, $group, '0', 'MKSTREAM']);
        } catch (\Throwable $e) {
            // group may already exist
        }

        $sensor = Sensor::factory()->create(['name' => 'Ghost', 'public_monitoring_enabled' => true]);
        $reading = app(SensorReadingService::class)->createReading($sensor, 42.0);
        $outbox = DomainEventOutbox::query()
            ->where('aggregate_id', (string) $reading->id)
            ->where('event_type', 'sensor.reading.created')
            ->firstOrFail();

        // The reading row disappears before delivery.
        SensorReading::query()->where('id', $reading->id)->delete();

        // The consumer keys the outbox off the `event_id` field (see DomainEventBroadcastConsumer),
        // matching DomainEventBroadcastConsumerTest::xadd().
        $conn->client()->executeRaw([
            'XADD', $stream, '*',
            'event_id', (string) $outbox->id,
            'event_type', 'sensor.reading.created', 'event_version', '1',
        ]);

        Event::fake([NewSensorReading::class]);

        $consumer = new DomainEventBroadcastConsumer(
            connection: $conn,
            stream: $stream,
            group: $group,
            deadLetterStream: 'test.durable-dlq',
        );
        $consumer->runOnce('worker-durable', 10, 100);

        Event::assertDispatched(NewSensorReading::class, function (NewSensorReading $event) use ($sensor) {
            $wire = $event->broadcastWith();
            $this->assertSame('Ghost', $wire['sensor_name']);
            $this->assertSame(42.0, $wire['value']);
            // public_at_occurrence=true → public channel present.
            $channels = (array) $event->broadcastOn();
            $hasPublic = collect($channels)->contains(fn ($c) => $c instanceof Channel && ! $c instanceof PrivateChannel);
            $this->assertTrue($hasPublic, 'event-time-public reading must reach the public channel');

            return true;
        });
    }

    /**
     * A HALF-enriched payload (carries sensor_name but is missing other required enrichment fields)
     * must NOT be broadcast as a fabricated null-riddled fact — it retries and dead-letters instead.
     * Requires real Redis (skipped as ENVIRONMENT_CONSTRAINT otherwise).
     */
    public function test_half_enriched_payload_is_dead_lettered_not_fabricated(): void
    {
        $host = env('TEST_REDIS_HOST', '127.0.0.1');
        $port = (int) env('TEST_REDIS_PORT', 6399);
        $redis = new RedisManager(app(), 'predis', [
            'client' => 'predis',
            'default' => ['host' => $host, 'port' => $port, 'database' => 15],
        ]);
        try {
            $conn = $redis->connection('default');
            $conn->client()->executeRaw(['PING']);
        } catch (\Throwable $e) {
            $this->markTestSkipped('ENVIRONMENT_CONSTRAINT: no Redis at '.$host.':'.$port);
        }

        $stream = 'test.durable-domain-events-half';
        $dlq = 'test.durable-dlq-half';
        $group = 'browser-delivery-v1';
        $conn->client()->executeRaw(['DEL', $stream]);
        $conn->client()->executeRaw(['DEL', $dlq]);
        try {
            $conn->client()->executeRaw(['XGROUP', 'CREATE', $stream, $group, '0', 'MKSTREAM']);
        } catch (\Throwable $e) {
            // group may already exist
        }

        $sensor = Sensor::factory()->create(['name' => 'Half']);
        $reading = app(SensorReadingService::class)->createReading($sensor, 7.0);
        $outbox = DomainEventOutbox::query()
            ->where('aggregate_id', (string) $reading->id)
            ->where('event_type', 'sensor.reading.created')
            ->firstOrFail();

        // Corrupt the stored payload into a half-enriched shape: keep sensor_name, drop the rest.
        $outbox->forceFill(['payload' => [
            'reading_id' => $reading->id,
            'sensor_id' => $sensor->id,
            'value' => 7.0,
            'reading_time' => now()->toIso8601String(),
            'sensor_name' => 'Half',
            // intentionally missing: sensor_type, unit, device_id, device_name, lab_id, lab_name, public_at_occurrence
        ]])->save();

        $conn->client()->executeRaw([
            'XADD', $stream, '*',
            'event_id', (string) $outbox->id,
            'event_type', 'sensor.reading.created', 'event_version', '1',
        ]);

        Event::fake([NewSensorReading::class]);

        $consumer = new DomainEventBroadcastConsumer(
            connection: $conn,
            stream: $stream,
            group: $group,
            deadLetterStream: $dlq,
        );
        // Exhaust attempts so the half-enriched message reaches the DLQ deterministically.
        for ($i = 0; $i < DomainEventBroadcastConsumer::MAX_ATTEMPTS + 1; $i++) {
            $consumer->runOnce('worker-half', 10, 10, 0);
        }

        Event::assertNotDispatched(NewSensorReading::class);

        $dlqLen = $conn->client()->executeRaw(['XLEN', $dlq]);
        $this->assertGreaterThanOrEqual(1, (int) $dlqLen, 'half-enriched payload must be dead-lettered');
        $this->assertNull($outbox->fresh()->delivered_at, 'a fabricated fact must never be marked delivered');
    }
}
