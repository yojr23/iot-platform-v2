<?php

namespace Tests\Unit;

use App\Models\RawSensorEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression for the MQTT-to-browser timezone defect: an ingestion `received_at` carrying a `Z`/UTC
 * offset was stored as the raw UTC digits, then read back via the `datetime` cast as APP_TIMEZONE,
 * shifting every MQTT-sourced reading_time hours into the future so the browser's clock-drift guard
 * dropped it and no live reading ever rendered. The RawSensorEvent `received_at` setter now
 * normalizes to the APP_TIMEZONE wall clock on write, matching SensorReadingService::normalizeReadingTime().
 */
class RawSensorEventReceivedAtTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Bogota']); // UTC-05, no DST
    }

    public function test_utc_z_received_at_is_stored_as_app_timezone_wall_clock(): void
    {
        $event = RawSensorEvent::create([
            'source' => 'tz-test',
            'source_event_id' => 'tz-1',
            'payload' => ['sensors' => []],
            'received_at' => '2026-09-19T17:45:30Z', // 17:45:30 UTC == 12:45:30 America/Bogota
            'status' => 'received',
        ]);

        // Stored digits must be the Bogota wall clock, not the raw UTC digits.
        $this->assertSame('2026-09-19 12:45:30', $event->fresh()->getRawOriginal('received_at'));
    }

    public function test_explicit_offset_received_at_is_stored_as_app_timezone_wall_clock(): void
    {
        $event = RawSensorEvent::create([
            'source' => 'tz-test',
            'source_event_id' => 'tz-2',
            'payload' => ['sensors' => []],
            'received_at' => '2026-09-19T15:00:00-02:00', // == 12:00:00 America/Bogota
            'status' => 'received',
        ]);

        $this->assertSame('2026-09-19 12:00:00', $event->fresh()->getRawOriginal('received_at'));
    }

    public function test_offsetless_received_at_is_interpreted_as_app_timezone_wall_clock(): void
    {
        // A legacy offsetless string is already an APP_TIMEZONE wall clock and must be preserved.
        $event = RawSensorEvent::create([
            'source' => 'tz-test',
            'source_event_id' => 'tz-3',
            'payload' => ['sensors' => []],
            'received_at' => '2026-09-19 12:30:00',
            'status' => 'received',
        ]);

        $this->assertSame('2026-09-19 12:30:00', $event->fresh()->getRawOriginal('received_at'));
    }

    public function test_null_received_at_stays_null(): void
    {
        $event = RawSensorEvent::create([
            'source' => 'tz-test',
            'source_event_id' => 'tz-4',
            'payload' => ['sensors' => []],
            'received_at' => null,
            'status' => 'received',
        ]);

        $this->assertNull($event->fresh()->received_at);
    }
}
