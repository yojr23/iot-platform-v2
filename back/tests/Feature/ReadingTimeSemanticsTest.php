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
 * Pre-Stage-6 Task 3 (PLAN.md Stage 6.0A preflight, `audit.md` §... "Before publishing a UTC
 * contract, run a DB/application timezone probe") — freezes how `sensor_readings.reading_time`
 * is actually stored/interpreted TODAY, before Stage 6.0B's UTC graph-range contract is designed.
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
 *   - `SensorReadingService::createReading()` accepts `DateTimeInterface|string|null` and passes
 *     it straight to `Eloquent::create()` (app/Services/Ingestion/SensorReadingService.php:18-24).
 *   - `SensorApiController::store()` (the real HTTP ingestion entrypoint) validates the
 *     `reading_time` request field with `nullable|date_format:Y-m-d H:i:s` only
 *     (app/Http/Controllers/Api/SensorApiController.php:63) — RFC3339 is not accepted there today.
 *
 * ENVIRONMENT GAP (do not read the assertions below as an executed/confirmed result until this
 * is closed): this file was authored and reviewed against source only. The authoring sandbox has
 * no `php` binary, no Docker, and no MySQL client on PATH, so this suite could not actually be
 * run. Close this GAP by running, from `back/`:
 *   APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array \
 *   SESSION_DRIVER=array QUEUE_CONNECTION=sync php artisan test --filter=ReadingTimeSemanticsTest
 * and recording the actual pass/fail in docs/implementation/reading-time-semantics.md before
 * treating the A/B/C classification there as final. This sqlite run can only prove/refute the
 * *application-level* (PHP/Eloquent cast) half of the question below — see that doc's separate
 * MySQL-session-timezone GAP for the half this suite cannot reach at all.
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
     * This does not assert a UTC storage contract that does not exist — it records what the
     * current code actually does with a UTC-instant input, per the task's requirement to prove
     * reality rather than assume it. Laravel's Eloquent `datetime` cast only forces a
     * `date_default_timezone_get()` (== config('app.timezone') == 'America/Bogota') conversion
     * when given a raw string/timestamp; when given an existing Carbon/DateTimeInterface
     * instance it wraps it as-is and formats *that instance's own timezone* for storage. A
     * caller that hands this path a UTC Carbon instance is therefore predicted to store the
     * literal UTC wall-clock digits with no offset marker — which the column's later read path
     * then re-interprets as Bogota wall clock. If confirmed, this alone is enough to classify
     * storage as mixed/ambiguous (C), independent of any MySQL-specific behavior.
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

        fwrite(STDERR, sprintf(
            "\n[reading-time-semantics] input=%s raw_db=%s cast=%s (tz=%s) iso8601=%s as_utc=%s\n",
            $instantUtc->toIso8601String(),
            $rawDbValue,
            $castValue->toDateTimeString(),
            $castValue->getTimezone()->getName(),
            $iso8601,
            $convertedToUtc->toIso8601String(),
        ));

        // Raw column value is a plain offsetless "Y-m-d H:i:s" string — no TZ marker is ever
        // written by this cast, on sqlite or otherwise.
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $rawDbValue);

        // Predicted: the stored digits equal the UTC wall clock of the input (15:00:00), NOT the
        // Bogota-converted wall clock (10:00:00) — because a DateTimeInterface instance bypasses
        // the raw-string Bogota-parsing branch of Eloquent's date cast.
        $this->assertSame('2026-09-09 15:00:00', $rawDbValue, 'GAP if this fails: re-run and update the classification in docs/implementation/reading-time-semantics.md.');
        $this->assertNotSame('2026-09-09 10:00:00', $rawDbValue);

        // The cast Carbon instance is then labeled Bogota (app default) over those UTC digits —
        // reading the value back does not recover the original instant.
        $this->assertSame('America/Bogota', $castValue->getTimezone()->getName());
        $this->assertStringStartsWith('2026-09-09T15:00:00', $iso8601);
        $this->assertStringEndsWith('-05:00', $iso8601);

        // Converting the mislabeled value to UTC shifts it a further 5 hours instead of
        // recovering "2026-09-09T15:00:00Z" — proof the round trip is lossy/ambiguous for a
        // UTC-instant caller, not just cosmetically offset.
        $this->assertNotSame($instantUtc->toIso8601String(), $convertedToUtc->toIso8601String());
    }

    /**
     * The legacy `Y-m-d H:i:s` string path (the only format `SensorApiController::store()`
     * accepts today) takes the *other* branch of the same cast: a raw string is parsed against
     * `date_default_timezone_get()` == Bogota, so it round-trips as a consistent Bogota
     * wall clock with no drift on re-read — the opposite of the DateTimeInterface case above.
     * This asymmetry (same column, two different effective semantics depending on caller input
     * type) is the concrete evidence for classification C.
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

        // Interpreted as Bogota, this instant equals 2026-09-09T15:00:00Z — i.e. the same
        // instant as the UTC test above, proving both paths *can* agree, but only if the caller
        // supplies a Bogota-local string for a Bogota-intended instant. A caller that means
        // "15:00 UTC" and sends the raw string "2026-09-09 15:00:00" (reusing UTC digits, as a
        // naive integration might) collides with this test's own input and is silently
        // mis-stored 5 hours off from the previous test's UTC-instant expectation.
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
     * A missing `reading_time` falls back to `now()` inside `SensorReadingService::createReading`
     * (app/Services/Ingestion/SensorReadingService.php:23) — `now()` resolves in
     * `date_default_timezone_get()` == Bogota, so this path is internally consistent with the
     * legacy-string path (both land in Bogota wall-clock terms), not with the UTC-instant path.
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
