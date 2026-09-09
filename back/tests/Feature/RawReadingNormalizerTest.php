<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\RawSensorEvent;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Services\Ingestion\RawReadingNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pre-Stage-6 Task 1 — proves `RawReadingNormalizer::normalize()` ->
 * `SensorReadingService::createReading()` stores the SAME physical instant no matter which PHP
 * type/format the timestamp arrives as. Before the fix, `SensorReadingService::createReading()`
 * passed `$readingTime` straight to Eloquent (`'reading_time' => $readingTime ?? now()`), so a
 * `DateTimeInterface` and an offsetless string took different branches of Eloquent's `datetime`
 * cast (see `ReadingTimeSemanticsTest` for the frozen evidence of that asymmetry). This suite
 * drives the real raw-ingestion entrypoint for all four cases so the fix is proven where the bug
 * actually lived, not just at the unit level.
 */
class RawReadingNormalizerTest extends TestCase
{
    use RefreshDatabase;

    /** Shared physical instant every case below must round-trip to: 2026-09-09T15:00:00Z ==
     *  2026-09-09 10:00:00 America/Bogota (UTC-05:00, no DST). */
    private const EXPECTED_INSTANT_ISO = '2026-09-09T15:00:00Z';

    private const EXPECTED_BOGOTA_WALL_CLOCK = '2026-09-09 10:00:00';

    /**
     * @return array{0: Device, 1: Sensor}
     */
    private function deviceAndSensor(string $serial): array
    {
        $device = Device::factory()->create([
            'serial_number' => $serial,
            'status' => true,
            'is_active' => true,
        ]);

        $sensor = Sensor::factory()->create([
            'device_id' => $device->id,
            'name' => 'temperature',
        ]);

        return [$device, $sensor];
    }

    private function assertReadingRoundTripsToExpectedInstant(Sensor $sensor, string $label): void
    {
        $reading = SensorReading::query()->where('sensor_id', $sensor->id)->sole();

        $rawDbValue = (string) DB::table('sensor_readings')->where('id', $reading->id)->value('reading_time');

        // Same instant, same caller-independent storage rule -> identical Bogota wall-clock digits.
        $this->assertSame(
            self::EXPECTED_BOGOTA_WALL_CLOCK,
            $rawDbValue,
            "{$label}: expected the shared instant to be stored as Bogota wall clock '".self::EXPECTED_BOGOTA_WALL_CLOCK."'",
        );

        $this->assertTrue(
            $reading->reading_time->equalTo(CarbonImmutable::parse(self::EXPECTED_INSTANT_ISO)),
            "{$label}: stored instant must equal ".self::EXPECTED_INSTANT_ISO,
        );
    }

    public function test_missing_payload_timestamp_falls_back_to_received_at_utc_carbon(): void
    {
        [, $sensor] = $this->deviceAndSensor('node-fallback-received-at');

        // `RawSensorEvent::$casts['received_at'] = 'datetime'` has the identical
        // caller-type-dependent storage bug this task fixes in SensorReadingService — but
        // RawSensorEvent is out of this task's scope (Inspect-only, not Modify). Assigning a UTC
        // Carbon through the normal Eloquent setter would collapse it to literal-digit string
        // ('2026-09-09 15:00:00') *before* RawReadingNormalizer ever runs, then re-read it back
        // mislabeled as Bogota — corrupting the fixture with an unrelated bug this task doesn't
        // own. setRawAttributes() bypasses that setter so `$event->received_at` genuinely is a
        // UTC-instant Carbon when RawReadingNormalizer reads it, isolating exactly what this task
        // DOES own: SensorReadingService's handling of a DateTimeInterface input.
        $event = RawSensorEvent::factory()->make([
            'node_id' => 'node-fallback-received-at',
            'payload' => [
                'sensors' => ['temperature' => ['value' => 21.5]],
            ],
        ]);
        $event->setRawAttributes(array_merge($event->getAttributes(), [
            'received_at' => CarbonImmutable::parse(self::EXPECTED_INSTANT_ISO),
        ]));

        $result = app(RawReadingNormalizer::class)->normalize($event);

        $this->assertSame(1, $result['created']);
        $this->assertReadingRoundTripsToExpectedInstant($sensor, 'missing payload timestamp + received_at UTC Carbon');
    }

    public function test_legacy_offsetless_payload_timestamp_is_interpreted_as_bogota_wall_clock(): void
    {
        [, $sensor] = $this->deviceAndSensor('node-legacy-string');

        $event = RawSensorEvent::factory()->create([
            'node_id' => 'node-legacy-string',
            'payload' => [
                'timestamp' => self::EXPECTED_BOGOTA_WALL_CLOCK,
                'sensors' => ['temperature' => ['value' => 21.5]],
            ],
        ]);

        $result = app(RawReadingNormalizer::class)->normalize($event);

        $this->assertSame(1, $result['created']);
        $this->assertReadingRoundTripsToExpectedInstant($sensor, 'legacy offsetless "Y-m-d H:i:s" payload timestamp');
    }

    public function test_rfc3339_z_payload_timestamp_round_trips_to_same_instant(): void
    {
        [, $sensor] = $this->deviceAndSensor('node-rfc3339-z');

        $event = RawSensorEvent::factory()->create([
            'node_id' => 'node-rfc3339-z',
            'payload' => [
                'timestamp' => self::EXPECTED_INSTANT_ISO,
                'sensors' => ['temperature' => ['value' => 21.5]],
            ],
        ]);

        $result = app(RawReadingNormalizer::class)->normalize($event);

        $this->assertSame(1, $result['created']);
        $this->assertReadingRoundTripsToExpectedInstant($sensor, 'RFC3339 "Z" payload timestamp');
    }

    public function test_rfc3339_explicit_offset_payload_timestamp_round_trips_to_same_instant(): void
    {
        [, $sensor] = $this->deviceAndSensor('node-rfc3339-offset');

        $event = RawSensorEvent::factory()->create([
            'node_id' => 'node-rfc3339-offset',
            'payload' => [
                'timestamp' => '2026-09-09T10:00:00-05:00',
                'sensors' => ['temperature' => ['value' => 21.5]],
            ],
        ]);

        $result = app(RawReadingNormalizer::class)->normalize($event);

        $this->assertSame(1, $result['created']);
        $this->assertReadingRoundTripsToExpectedInstant($sensor, 'RFC3339 explicit-offset payload timestamp');
    }
}
