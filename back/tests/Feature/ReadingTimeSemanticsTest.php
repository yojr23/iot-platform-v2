<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Services\Ingestion\SensorReadingService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pre-Stage-6 Task 1/3 (PLAN.md Stage 6.0A preflight, `audit.md` §... "Before publishing a UTC
 * contract, run a DB/application timezone probe") — documents how `sensor_readings.reading_time`
 * is stored/interpreted, before Stage 6.0B's UTC graph-range contract is designed.
 *
 * Existing code reused: exercises the real write path unmodified —
 * `App\Services\Ingestion\SensorReadingService::createReading()` (the same call
 * `SensorApiController::store()` makes) and `App\Models\SensorReading`'s existing
 * `'reading_time' => 'datetime'` cast (`app/Models/SensorReading.php:16`). No new
 * ingestion/storage path is introduced by this test.
 * Existing owner retired/delegated: n/a — this is a read-only/observational test file.
 * Compatibility window: n/a.
 *
 * VERIFIED FACTS this test is built on (read from source, see
 * docs/implementation/reading-time-semantics.md for the full evidence trail):
 *   - config('app.timezone') === 'America/Bogota' (config/app.php:67), fixed UTC-05:00, no DST.
 *   - `sensor_readings.reading_time` is a Laravel `timestamp` column
 *     (database/migrations/2025_04_29_134329_create_sensor_readings_table.php:18).
 *   - `SensorReadingService::createReading()` accepts `DateTimeInterface|string|null` and, as of
 *     Pre-Stage-6 Task 1, normalizes it through a private `normalizeReadingTime()` method before
 *     handing Eloquent a single unambiguous APP_TIMEZONE wall-clock string
 *     (app/Services/Ingestion/SensorReadingService.php) — caller PHP type no longer selects
 *     storage semantics.
 *   - `SensorApiController::store()` (the real HTTP ingestion entrypoint) validates the
 *     `reading_time` request field with `nullable|date_format:Y-m-d H:i:s` only
 *     (app/Http/Controllers/Api/SensorApiController.php:63) — RFC3339 is not accepted there today.
 *
 * This suite has been executed against MySQL via `docker compose exec -T back php artisan test
 * --filter=ReadingTimeSemanticsTest` (Pre-Stage-6 Task 1) — see
 * docs/implementation/reading-time-semantics.md for the recorded pass/fail evidence and the
 * resolved classification (previously frozen as C — mixed/ambiguous).
 */
class ReadingTimeSemanticsTest extends TestCase
{
    use RefreshDatabase;

    private function sensor(): Sensor
    {
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);

        return Sensor::factory()->create(['device_id' => $device->id]);
    }

    /**
     * Fixed-instant round trip through the real ingestion path, feeding a `DateTimeInterface`
     * value that is unambiguously UTC ($instantUtc = 2026-09-09T15:00:00Z, i.e. 2026-09-09
     * 10:00:00 Bogota wall clock).
     *
     * Pre-Stage-6 Task 1 RESOLVED the ambiguity this test originally documented:
     * `SensorReadingService::createReading()` now converts any `DateTimeInterface` input to
     * APP_TIMEZONE (America/Bogota) before formatting it for storage, instead of writing the
     * instance's own (UTC) digits verbatim. This test now proves the instant is preserved
     * end-to-end, not lost 5 hours off on read-back.
     */
    public function test_fixed_utc_instant_round_trip_through_the_real_ingestion_path(): void
    {
        $instantUtc = CarbonImmutable::parse('2026-09-09T15:00:00Z');
        $sensor = $this->sensor();

        $reading = app(SensorReadingService::class)->createReading($sensor, 42.0, $instantUtc);

        $rawDbValue = (string) DB::table('sensor_readings')->where('id', $reading->id)->value('reading_time');
        $castValue = $reading->reading_time;
        $iso8601 = $castValue->toIso8601String();
        $convertedToUtc = $castValue->clone()->setTimezone('UTC');

        // Raw column value is a plain offsetless "Y-m-d H:i:s" string — the normalized
        // APP_TIMEZONE wall clock, not the raw UTC digits.
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $rawDbValue);

        // The instant is preserved and converted: 15:00:00 UTC == 10:00:00 Bogota wall clock.
        $this->assertSame('2026-09-09 10:00:00', $rawDbValue);
        $this->assertNotSame('2026-09-09 15:00:00', $rawDbValue);

        $this->assertSame('America/Bogota', $castValue->getTimezone()->getName());
        $this->assertStringStartsWith('2026-09-09T10:00:00', $iso8601);
        $this->assertStringEndsWith('-05:00', $iso8601);

        // Converting back to UTC recovers the original instant exactly — no drift, no loss.
        $this->assertSame($instantUtc->toIso8601String(), $convertedToUtc->toIso8601String());
    }

    /**
     * The legacy `Y-m-d H:i:s` string path (the only format `SensorApiController::store()`
     * accepts today) is interpreted explicitly as an APP_TIMEZONE (Bogota) wall clock by
     * `SensorReadingService::normalizeReadingTime()`, so it round-trips with no drift on re-read
     * — and, per Pre-Stage-6 Task 1's fix, now agrees with the `DateTimeInterface` case above for
     * the same physical instant, instead of the two diverging by caller input type.
     */
    public function test_legacy_offsetless_string_is_interpreted_as_bogota_wall_clock(): void
    {
        $sensor = $this->sensor();

        $reading = app(SensorReadingService::class)->createReading($sensor, 10.0, '2026-09-09 10:00:00');

        $rawDbValue = (string) DB::table('sensor_readings')->where('id', $reading->id)->value('reading_time');
        $castValue = $reading->reading_time;

        // No shift: the literal string passed in is what lands in the column.
        $this->assertSame('2026-09-09 10:00:00', $rawDbValue);
        $this->assertSame('America/Bogota', $castValue->getTimezone()->getName());
        $this->assertStringEndsWith('-05:00', $castValue->toIso8601String());

        // Interpreted as Bogota, this instant equals 2026-09-09T15:00:00Z — the SAME instant the
        // UTC-DateTimeInterface test above now converges on, confirming the two input types are
        // no longer ambiguous with each other.
        $this->assertSame('2026-09-09T15:00:00Z', $castValue->clone()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z'));
    }

    /**
     * RFC3339 `...Z` is rejected by the real HTTP ingestion validation today — freezing this so
     * a future UTC contract change is a deliberate decision, not an accidental behavior change.
     */
    public function test_rfc3339_utc_z_format_is_rejected_by_current_ingestion_validation(): void
    {
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);
        config(['app.api_key' => 'valid-key']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 10.0,
            'reading_time' => '2026-09-09T15:00:00Z',
            'api_key' => 'valid-key',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reading_time']);
        $this->assertDatabaseCount('sensor_readings', 0);
    }

    /**
     * RFC3339 with an explicit offset is rejected the same way — same validation rule, same gap.
     */
    public function test_rfc3339_explicit_offset_format_is_rejected_by_current_ingestion_validation(): void
    {
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);
        config(['app.api_key' => 'valid-key']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 10.0,
            'reading_time' => '2026-09-09T10:00:00-05:00',
            'api_key' => 'valid-key',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reading_time']);
        $this->assertDatabaseCount('sensor_readings', 0);
    }

    /**
     * A missing `reading_time` falls back to `Carbon::now(APP_TIMEZONE)` inside
     * `SensorReadingService::normalizeReadingTime()` — Bogota wall-clock terms, consistent with
     * every other input branch after Pre-Stage-6 Task 1's fix.
     */
    public function test_missing_reading_time_defaults_to_bogota_now(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-09-09 10:00:00', 'America/Bogota'));

        try {
            $sensor = $this->sensor();

            $reading = app(SensorReadingService::class)->createReading($sensor, 10.0, null);

            $rawDbValue = (string) DB::table('sensor_readings')->where('id', $reading->id)->value('reading_time');

            $this->assertSame('2026-09-09 10:00:00', $rawDbValue);
            $this->assertSame('America/Bogota', $reading->reading_time->getTimezone()->getName());
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Sanity check that this file's `DB::table()` raw-value reads are actually looking at
     * something structurally different from the Eloquent-cast accessor, so the assertions above
     * are proving a real distinction and not a tautology.
     */
    public function test_raw_db_value_and_cast_value_are_different_representations(): void
    {
        $sensor = $this->sensor();
        $reading = app(SensorReadingService::class)->createReading($sensor, 1.0, '2026-09-09 10:00:00');
        $fresh = SensorReading::query()->findOrFail($reading->id);

        $rawDbValue = DB::table('sensor_readings')->where('id', $reading->id)->value('reading_time');

        $this->assertIsString($rawDbValue);
        $this->assertInstanceOf(Carbon::class, $fresh->reading_time);
    }
}
