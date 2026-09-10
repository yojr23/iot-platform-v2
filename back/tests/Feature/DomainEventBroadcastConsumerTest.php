<?php

namespace Tests\Feature;

use App\Events\AlertResolved;
use App\Events\DeviceStatusUpdated;
use App\Events\NewAlertTriggered;
use App\Events\NewSensorReading;
use App\Models\Alert;
use App\Models\Device;
use App\Models\DomainEventOutbox;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SensorType;
use App\Services\Ingestion\DomainEventBroadcastConsumer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
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

    private function triggeredAlertOutbox(): DomainEventOutbox
    {
        $alert = Alert::factory()->create(['resolved' => false, 'resolved_at' => null]);

        return DomainEventOutbox::factory()->create([
            'event_type' => 'alert.triggered',
            'aggregate_type' => 'alert',
            'aggregate_id' => (string) $alert->id,
            'payload' => ['alert_id' => $alert->id],
            'status' => 'published',
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
            'payload' => [
                'device_id' => $device->id,
                'status' => false,
                'is_active' => false,
                'changed_at' => now()->toIso8601String(),
            ],
            'status' => 'published',
        ]);
    }

    private function sensorReadingOutbox(?bool $publicMonitoringEnabled = null): DomainEventOutbox
    {
        $sensor = $publicMonitoringEnabled === null
            ? Sensor::factory()->create()
            : Sensor::factory()->create(['public_monitoring_enabled' => $publicMonitoringEnabled]);

        $reading = SensorReading::factory()->create(['sensor_id' => $sensor->id]);

        return DomainEventOutbox::factory()->create([
            'event_type' => 'sensor.reading.created',
            'aggregate_type' => 'sensor_reading',
            'aggregate_id' => (string) $reading->id,
            'payload' => ['reading_id' => $reading->id],
            'status' => 'published',
        ]);
    }

    public function test_alert_triggered_fact_is_broadcast_exactly_once(): void
    {
        Event::fake([NewAlertTriggered::class]);

        $outbox = $this->triggeredAlertOutbox();
        $this->xadd($outbox->id, 'alert.triggered');

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $stats['acked']);
        Event::assertDispatchedTimes(NewAlertTriggered::class, 1);
        $this->assertNotNull($outbox->fresh()->delivered_at);
    }

    public function test_duplicate_delivery_of_alert_triggered_broadcasts_only_once(): void
    {
        Event::fake([NewAlertTriggered::class]);

        $outbox = $this->triggeredAlertOutbox();
        // Same outbox row delivered twice (at-least-once relay) must stay idempotent.
        $this->xadd($outbox->id, 'alert.triggered');
        $this->xadd($outbox->id, 'alert.triggered');

        $this->consumer()->runOnce('worker-A', 10, 100);

        Event::assertDispatchedTimes(NewAlertTriggered::class, 1);
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

    /**
     * Gate 8: the event is built straight from the immutable outbox payload — no `Device::find()`
     * re-read — and broadcasts on the PRIVATE `device-status` channel (moved off the public
     * `Channel` at Gate 8, device status is not guest data).
     */
    public function test_device_status_changed_broadcasts_on_private_channel_with_immutable_payload(): void
    {
        Event::fake([DeviceStatusUpdated::class]);

        $device = Device::factory()->create(['status' => false, 'is_active' => false]);
        $outbox = DomainEventOutbox::factory()->create([
            'event_type' => 'device.status.changed',
            'aggregate_type' => 'device',
            'aggregate_id' => (string) $device->id,
            'payload' => [
                'device_id' => $device->id,
                'status' => true,
                'is_active' => true,
                'changed_at' => '2026-01-01T00:00:00+00:00',
            ],
            'status' => 'published',
        ]);
        $this->xadd($outbox->id, 'device.status.changed');

        $this->consumer()->runOnce('worker-A', 10, 100);

        Event::assertDispatched(DeviceStatusUpdated::class, function (DeviceStatusUpdated $event) use ($device, $outbox) {
            $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn());
            $this->assertSame('private-device-status', $event->broadcastOn()->name);

            // Payload comes from the outbox row (captured at write time), not a live Device row —
            // even though the row currently says false/false, the fact says true/true.
            $this->assertSame($device->id, $event->deviceId);
            $this->assertTrue($event->status);
            $this->assertTrue($event->isActive);
            $this->assertSame('2026-01-01T00:00:00+00:00', $event->changedAt);
            $this->assertSame($outbox->id, $event->eventSequence);

            return true;
        });
    }

    /**
     * Gate 8 regression: two rapid status transitions must each keep their own value in their own
     * fact, and `event_sequence` must strictly increase — the bug this closes is the consumer
     * re-reading the *current* Device row so an earlier fact's broadcast could carry a later fact's
     * status.
     */
    public function test_two_rapid_device_status_facts_keep_independent_payload_and_increasing_sequence(): void
    {
        Event::fake([DeviceStatusUpdated::class]);

        $device = Device::factory()->create(['status' => true, 'is_active' => true]);

        $offOutbox = DomainEventOutbox::factory()->create([
            'event_type' => 'device.status.changed',
            'aggregate_type' => 'device',
            'aggregate_id' => (string) $device->id,
            'payload' => ['device_id' => $device->id, 'status' => false, 'is_active' => false, 'changed_at' => now()->toIso8601String()],
            'status' => 'published',
        ]);
        $onOutbox = DomainEventOutbox::factory()->create([
            'event_type' => 'device.status.changed',
            'aggregate_type' => 'device',
            'aggregate_id' => (string) $device->id,
            'payload' => ['device_id' => $device->id, 'status' => true, 'is_active' => true, 'changed_at' => now()->toIso8601String()],
            'status' => 'published',
        ]);

        // Device row's *current* value is ON, but the first fact must still broadcast OFF — proves
        // the consumer never reloads the live row.
        $this->xadd($offOutbox->id, 'device.status.changed');
        $this->xadd($onOutbox->id, 'device.status.changed');

        $this->consumer()->runOnce('worker-A', 10, 100);

        $dispatched = [];
        Event::assertDispatched(DeviceStatusUpdated::class, function (DeviceStatusUpdated $event) use (&$dispatched) {
            $dispatched[] = $event;

            return true;
        });

        $this->assertCount(2, $dispatched);
        $this->assertFalse($dispatched[0]->status);
        $this->assertTrue($dispatched[1]->status);
        $this->assertTrue($dispatched[1]->eventSequence > $dispatched[0]->eventSequence);
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

    /**
     * Stage 6.0 update (was pre-Stage-6 preflight, hardcoded `includePublicChannel: true`): the
     * real dispatch site now opts into the public channel only via
     * `PublicGraphVisibility::isPublic()`. For an explicitly public sensor both the public and the
     * authenticated private channel are still populated — unchanged end result for this case, now
     * policy-driven instead of a literal `true`. The restricted-sensor case (public channel
     * suppressed) is covered by `test_restricted_sensor_fact_is_acked_without_public_reading_event`.
     */
    public function test_sensor_reading_created_is_broadcast_on_both_public_and_private_channels(): void
    {
        Event::fake([NewSensorReading::class]);

        $outbox = $this->sensorReadingOutbox(publicMonitoringEnabled: true);
        $this->xadd($outbox->id, 'sensor.reading.created');

        $this->consumer()->runOnce('worker-A', 10, 100);

        Event::assertDispatched(NewSensorReading::class, function (NewSensorReading $event) {
            $channels = $event->broadcastOn();
            $this->assertIsArray($channels);
            $this->assertCount(2, $channels);

            $public = array_values(array_filter($channels, fn ($c) => $c instanceof Channel && ! $c instanceof PrivateChannel));
            $private = array_values(array_filter($channels, fn ($c) => $c instanceof PrivateChannel));

            $this->assertCount(1, $public);
            $this->assertCount(1, $private);
            $this->assertSame('sensor.'.$event->reading->sensor_id, $public[0]->name);
            // Laravel's PrivateChannel prefixes the wire name with `private-`.
            $this->assertSame('private-sensor.'.$event->reading->sensor_id, $private[0]->name);

            return true;
        });
    }

    /**
     * NewSensorReading::broadcastOn() audience flags, exercised directly (no Redis needed):
     * Gate 6 fail-closed default — the un-flagged construction path broadcasts on NO channel —
     * and each explicit flag combination produces the expected channel set.
     */
    public function test_new_sensor_reading_broadcast_on_honors_audience_flags(): void
    {
        $sensorType = SensorType::factory()->create();
        $sensor = Sensor::factory()->create(['sensor_type_id' => $sensorType->id]);
        $reading = SensorReading::factory()->create(['sensor_id' => $sensor->id]);

        // Default (no flags passed): fail-closed — no channels at all.
        $default = new NewSensorReading($reading);
        $this->assertSame([], $default->broadcastOn());

        // Public-only, explicit.
        $publicOnly = new NewSensorReading($reading, includePublicChannel: true, includePrivateChannel: false);
        $channel = $publicOnly->broadcastOn();
        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertNotInstanceOf(PrivateChannel::class, $channel);

        // Private-only.
        $privateOnly = new NewSensorReading($reading, includePublicChannel: false, includePrivateChannel: true);
        $channel = $privateOnly->broadcastOn();
        $this->assertInstanceOf(PrivateChannel::class, $channel);
        $this->assertSame('private-sensor.'.$sensor->id, $channel->name);

        // Both.
        $both = new NewSensorReading($reading, includePublicChannel: true, includePrivateChannel: true);
        $channels = $both->broadcastOn();
        $this->assertIsArray($channels);
        $this->assertCount(2, $channels);
    }

    /**
     * PLAN.md Stage 6.0 Task 4: the dispatch site now asks `PublicGraphVisibility` instead of
     * hardcoding `includePublicChannel: true`. An explicitly public sensor's fact is delivered on
     * both channels, unchanged from pre-Stage-6 behavior.
     */
    public function test_explicitly_public_sensor_reading_is_broadcast_on_public_and_private_channels(): void
    {
        Event::fake([NewSensorReading::class]);

        $outbox = $this->sensorReadingOutbox(publicMonitoringEnabled: true);
        $this->xadd($outbox->id, 'sensor.reading.created');

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $stats['acked']);
        Event::assertDispatched(NewSensorReading::class, function (NewSensorReading $event) {
            $channels = $event->broadcastOn();
            $this->assertIsArray($channels);
            $this->assertCount(2, $channels);

            $public = array_values(array_filter($channels, fn ($c) => $c instanceof Channel && ! $c instanceof PrivateChannel));
            $this->assertCount(1, $public);

            return true;
        });
        $this->assertNotNull($outbox->fresh()->delivered_at);
    }

    /**
     * PLAN.md Stage 6.0 Task 4: a restricted sensor's fact is still a successful terminal
     * delivery outcome (acked, `delivered_at` set) but never reaches the public channel. The
     * private channel keeps working unchanged for authorized viewers.
     */
    public function test_restricted_sensor_fact_is_acked_without_public_reading_event(): void
    {
        Event::fake([NewSensorReading::class]);

        $outbox = $this->sensorReadingOutbox(publicMonitoringEnabled: false);
        $this->xadd($outbox->id, 'sensor.reading.created');

        $stats = $this->consumer()->runOnce('worker-A', 10, 100);

        $this->assertSame(1, $stats['acked']);
        Event::assertDispatched(NewSensorReading::class, function (NewSensorReading $event) {
            $channels = $event->broadcastOn();
            $this->assertIsArray($channels);
            $this->assertCount(1, $channels);
            $this->assertInstanceOf(PrivateChannel::class, $channels[0]);

            return true;
        });
        $this->assertNotNull($outbox->fresh()->delivered_at);
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
