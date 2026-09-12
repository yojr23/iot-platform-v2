<?php

namespace Tests\Feature;

use App\Models\DomainEventOutbox;
use App\Models\RawSensorEvent;
use App\Services\Ingestion\DomainEventPublisher;
use App\Services\Ingestion\RawSensorEventPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Regression freeze for a live-only delivery break the mocked CDC tests could NOT catch: the
 * publishers wrote via the Redis facade (which prepends Laravel's key prefix,
 * `iot_platform_v2_back_database_`), while every consumer + Debezium reads the stream with RAW,
 * UNPREFIXED commands. Result: XADD landed on `<prefix>iot.raw-events` but the consumer read
 * `iot.raw-events` — they never met, so readings were never created in the running Docker stack.
 *
 * This test uses the REAL publisher against REAL Redis and asserts the entry is readable at the
 * unprefixed key (the exact name the raw-command consumer uses). It fails if a publisher ever goes
 * back to the prefixing facade path.
 */
class PublisherStreamPrefixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            Redis::connection('default')->client();
            Redis::command('ping', []);
        } catch (\Throwable $e) {
            $this->markTestSkipped('ENVIRONMENT_CONSTRAINT: no Redis available ('.$e->getMessage().')');
        }
    }

    public function test_raw_publisher_writes_to_the_unprefixed_stream_the_consumer_reads(): void
    {
        $stream = 'test.prefix.raw-events';
        config(['app.ingestion_raw_events_stream' => $stream]);

        $conn = Redis::connection('default');
        $this->rawDel($conn, $stream);

        $event = RawSensorEvent::create([
            'topic' => 'test', 'source' => 'test', 'source_event_id' => 'pfx-'.uniqid(),
            'node_id' => 'SN-PFX', 'payload' => ['sensors' => []],
            'received_at' => now(), 'status' => 'received',
        ]);

        $this->assertTrue(app(RawSensorEventPublisher::class)->publish($event));

        // Read with a RAW (unprefixed) command — exactly what the consumer does. XLEN must be 1.
        $len = (int) $this->rawXlen($conn, $stream);
        $this->assertSame(1, $len, 'Raw publisher must XADD to the unprefixed stream the consumer reads.');

        $this->rawDel($conn, $stream);
    }

    public function test_domain_publisher_writes_to_the_unprefixed_stream_the_consumer_reads(): void
    {
        $stream = 'test.prefix.domain-events';
        config(['app.domain_events_stream' => $stream]);

        $conn = Redis::connection('default');
        $this->rawDel($conn, $stream);

        $outbox = DomainEventOutbox::create([
            'event_type' => 'sensor.reading.created',
            'aggregate_type' => 'sensor_reading',
            'aggregate_id' => '1',
            'payload' => ['reading_id' => 1],
            'status' => 'pending',
        ]);

        $this->assertTrue(app(DomainEventPublisher::class)->publish($outbox));

        $len = (int) $this->rawXlen($conn, $stream);
        $this->assertSame(1, $len, 'Domain publisher must XADD to the unprefixed stream the consumer reads.');

        $this->rawDel($conn, $stream);
    }

    public function test_raw_publisher_approximately_trims_its_configured_stream_while_a_consumer_group_can_read_it(): void
    {
        $stream = 'test.retention.raw-events.'.uniqid();
        $group = 'retention-readers';
        config([
            'app.ingestion_raw_events_stream' => $stream,
            // Redis approximate trimming works in macro-node sized batches, so the assertion
            // below deliberately tests a bounded retained stream rather than exact length 10.
            'app.ingestion_raw_events_maxlen' => 10,
        ]);

        $conn = Redis::connection('default');
        $this->rawDel($conn, $stream);

        try {
            $publisher = app(RawSensorEventPublisher::class);
            for ($id = 1; $id <= 250; $id++) {
                $event = new RawSensorEvent([
                    'topic' => 'retention/test',
                    'source' => 'test',
                    'source_event_id' => 'retention-'.$id,
                    'node_id' => 'SN-RETENTION',
                    'received_at' => now(),
                    'status' => 'received',
                ]);
                $event->id = $id;

                $this->assertTrue($publisher->publish($event));
            }

            $length = (int) $this->rawXlen($conn, $stream);
            $this->assertGreaterThanOrEqual(10, $length);
            $this->assertLessThanOrEqual(110, $length, 'MAXLEN ~ must bound stream growth, even though trimming is approximate.');

            $this->raw($conn, ['XGROUP', 'CREATE', $stream, $group, '0']);
            $entries = $this->raw($conn, ['XREADGROUP', 'GROUP', $group, 'consumer-1', 'COUNT', 1, 'STREAMS', $stream, '>']);

            $this->assertNotEmpty($entries, 'A group created after trimming must still be able to read retained entries.');
        } finally {
            $this->rawDel($conn, $stream);
        }
    }

    private function rawXlen($conn, string $stream): mixed
    {
        $client = $conn->client();

        return $client instanceof \Redis
            ? $client->rawCommand('XLEN', $stream)
            : $client->executeRaw(['XLEN', $stream]);
    }

    private function rawDel($conn, string $stream): void
    {
        $this->raw($conn, ['DEL', $stream]);
    }

    private function raw($conn, array $args): mixed
    {
        $client = $conn->client();

        return $client instanceof \Redis
            ? $client->rawCommand(...$args)
            : $client->executeRaw($args);
    }
}
