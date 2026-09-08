<?php

namespace Tests\Unit;

use App\Models\RawSensorEvent;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PLAN.md Stage 2.5 / docs/implementation/adr-g1.md — schema-level idempotency backstop for
 * duplicate producer retries sharing the same (source, source_event_id).
 */
class RawSensorEventIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_source_and_source_event_id_is_rejected_by_the_database(): void
    {
        RawSensorEvent::create([
            'source' => 'ingestion_service',
            'source_event_id' => 'dup-1',
            'payload' => ['sensors' => []],
            'status' => 'received',
        ]);

        $this->expectException(QueryException::class);

        RawSensorEvent::create([
            'source' => 'ingestion_service',
            'source_event_id' => 'dup-1',
            'payload' => ['sensors' => []],
            'status' => 'received',
        ]);
    }

    public function test_multiple_null_source_event_ids_are_allowed(): void
    {
        RawSensorEvent::create([
            'source' => 'ingestion_service',
            'source_event_id' => null,
            'payload' => ['sensors' => []],
            'status' => 'received',
        ]);

        RawSensorEvent::create([
            'source' => 'ingestion_service',
            'source_event_id' => null,
            'payload' => ['sensors' => []],
            'status' => 'received',
        ]);

        $this->assertDatabaseCount('raw_sensor_events', 2);
    }

    public function test_same_source_event_id_from_different_sources_is_allowed(): void
    {
        RawSensorEvent::create([
            'source' => 'ingestion_service',
            'source_event_id' => 'shared-id',
            'payload' => ['sensors' => []],
            'status' => 'received',
        ]);

        RawSensorEvent::create([
            'source' => 'mqtt-bridge',
            'source_event_id' => 'shared-id',
            'payload' => ['sensors' => []],
            'status' => 'received',
        ]);

        $this->assertDatabaseCount('raw_sensor_events', 2);
    }
}
