<?php

namespace Tests\Unit;

use App\Models\DomainEventOutbox;
use App\Services\Ingestion\DomainEventPublisher;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Regression for the phpredis xAdd arity bug: the publishers used to flatten the field map into
 * positional key/value args (`[$stream,'*','k1','v1','k2','v2',...]`), which phpredis rejects with
 * "xadd() expects at most 6 arguments, 18 given". phpredis's signature is
 * `xAdd(key, id, array $fields)` — fields must be ONE associative array. The existing publisher
 * tests mock the whole publisher / Redis away, so they never exercised the real call shape; this
 * asserts the exact argument structure handed to `Redis::command('xadd', ...)`.
 */
class EventPublisherXaddShapeTest extends TestCase
{
    public function test_domain_event_publisher_passes_fields_as_single_associative_array(): void
    {
        $captured = null;
        Redis::shouldReceive('command')
            ->once()
            ->with('xadd', \Mockery::on(function ($args) use (&$captured) {
                $captured = $args;

                return true;
            }))
            ->andReturn('1-0');

        $outbox = new DomainEventOutbox([
            'event_type' => 'sensor.reading.created',
            'aggregate_type' => 'sensor_reading',
            'aggregate_id' => '7',
            'payload' => ['reading_id' => 7, 'value' => 1.5],
        ]);
        $outbox->id = 42;

        $result = (new DomainEventPublisher())->publish($outbox);

        $this->assertTrue($result);
        // [stream, '*', assoc-fields] — exactly 3 args, third is the assoc map.
        $this->assertCount(3, $captured);
        $this->assertSame('*', $captured[1]);
        $this->assertIsArray($captured[2]);
        $this->assertArrayHasKey('event_type', $captured[2]);
        $this->assertSame('sensor.reading.created', $captured[2]['event_type']);
        // Guard against the regression: no flattened positional field values.
        $this->assertArrayNotHasKey(3, $captured);
    }
}
