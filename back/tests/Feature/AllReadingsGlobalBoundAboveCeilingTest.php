<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\SensorApiController;
use App\Models\Device;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SensorType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PENDING CI EXECUTION — written on Windows, executed by GitHub Actions.
 *
 * Task 5 regression coverage: redesigns `allReadings()`'s bound so the sensor-count CAP itself
 * (floor(BUDGET/2)) guarantees every SELECTED sensor gets >= 1 reading, replacing the prior
 * "reserve sensor-metadata budget then split the remainder" draft (commit 9583230) that could
 * starve every sensor to ZERO readings once sensorsLoaded alone consumed the whole 5,000-row
 * budget (roughly 2,500-5,000+ sensors — perSensorLimit floored to 0 via integer division).
 *
 * Asserts, at 3,000-5,000 sensors:
 *  (a) total rows (sensors + readings) never exceed the global budget;
 *  (b) EVERY returned sensor has >= 1 reading — the exact regression the redesign fixes;
 *  (c) truncation is reported (not silently dropped) when the sensor count is capped.
 *
 * Uses a raw bulk `DB::table('sensors')->insert()` (bypassing Eloquent/observers) to create sensor
 * rows quickly, plus a handful of real readings per sensor within the returned window so "every
 * sensor has >= 1 reading" is a meaningful assertion and not vacuously true because no sensor has
 * any reading at all.
 */
class AllReadingsGlobalBoundAboveCeilingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function token(): string
    {
        $role = Role::create([
            'code' => 'bound-test-'.uniqid(),
            'name' => 'Bound Test Role',
            'description' => 'test',
            'is_system' => false,
            'level' => 10,
        ]);
        $permIds = Permission::whereIn('code', ['sensor_reading.view'])->pluck('id');
        $role->permissions()->sync($permIds);

        return User::factory()->create(['role_id' => $role->id])->createToken('t', ['read'])->plainTextToken;
    }

    /**
     * Bulk-creates $totalSensors sensors and gives the FIRST $sensorsWithReadings of them one
     * reading each inside the default (last 24h) window, via raw inserts for speed.
     */
    private function seedSensorsWithReadings(int $totalSensors, int $sensorsWithReadings): array
    {
        $device = Device::factory()->create();
        $sensorType = SensorType::factory()->create();
        $now = now();

        $rows = [];
        for ($i = 1; $i <= $totalSensors; $i++) {
            $rows[] = [
                'device_id' => $device->id,
                'sensor_type_id' => $sensorType->id,
                'name' => 'bulk-sensor-'.$i,
                'status' => true,
                'public_monitoring_enabled' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 500) {
                DB::table('sensors')->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('sensors')->insert($rows);
        }

        // Only the sensors sorted first by id (the CAP's own ordering) can end up in the response,
        // so seed readings on the lowest-id sensors to make "every returned sensor has >= 1
        // reading" a real (non-vacuous) assertion.
        $lowestIds = DB::table('sensors')->orderBy('id')->limit($sensorsWithReadings)->pluck('id');
        $readingRows = $lowestIds->map(fn ($sensorId) => [
            'sensor_id' => $sensorId,
            'value' => 42.5,
            'reading_time' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        foreach (array_chunk($readingRows, 500) as $chunk) {
            DB::table('sensor_readings')->insert($chunk);
        }

        return ['device' => $device, 'sensor_type' => $sensorType];
    }

    public static function sensorCountProvider(): array
    {
        return [
            '3000 sensors' => [3000],
            '4000 sensors' => [4000],
            '5000 sensors' => [5000],
        ];
    }

    /**
     * @dataProvider sensorCountProvider
     */
    public function test_every_returned_sensor_has_at_least_one_reading_and_budget_is_respected(int $totalSensors): void
    {
        $budget = SensorApiController::ALL_READINGS_GLOBAL_ROW_BUDGET;
        $this->seedSensorsWithReadings($totalSensors, $totalSensors);

        $this->assertSame($totalSensors, DB::table('sensors')->count(), 'test setup sanity check');

        $response = $this->withToken($this->token())
            ->getJson('/api/sensors/all/readings')
            ->assertOk();

        $sensors = $response->json('sensors');

        // (a) Total rows (sensor metadata rows + reading rows) never exceed the global budget.
        $totalReadingRows = collect($sensors)->sum(fn ($s) => count($s['readings']));
        $this->assertLessThanOrEqual(
            $budget,
            count($sensors) + $totalReadingRows,
            'sensors + readings combined must respect the single global row ceiling'
        );

        // (b) THE regression this redesign fixes: every returned sensor must have >= 1 reading,
        // never zero, regardless of how many sensors exist in the table.
        $this->assertNotEmpty($sensors, 'expected at least one sensor in the response');
        foreach ($sensors as $sensor) {
            $this->assertGreaterThanOrEqual(
                1,
                count($sensor['readings']),
                "sensor id {$sensor['id']} was returned with zero readings — the exact regression task 5 fixes"
            );
        }

        // (c) Truncation is surfaced, not silently dropped, whenever the sensor cap applied
        // (any sensor count > floor(BUDGET/2) drops the excess sensors from this response).
        $maxSensorsForGuaranteedReading = intdiv($budget, 2);
        $expectedTruncated = $totalSensors > $maxSensorsForGuaranteedReading;
        $this->assertSame($expectedTruncated, $response->json('sensors_truncated'));
        $this->assertSame($totalSensors, $response->json('total_sensors'));
        if ($expectedTruncated) {
            $this->assertGreaterThan(0, $response->json('sensors_dropped_count'));
            $this->assertLessThanOrEqual($maxSensorsForGuaranteedReading, count($sensors));
        } else {
            $this->assertSame(0, $response->json('sensors_dropped_count'));
        }
    }

    public function test_sensor_count_in_response_never_exceeds_the_global_budget_with_more_sensors_than_the_ceiling(): void
    {
        $budget = SensorApiController::ALL_READINGS_GLOBAL_ROW_BUDGET;
        $totalSensors = $budget + 500; // deliberately above the ceiling

        $this->seedSensorsWithReadings($totalSensors, 0);

        $this->assertGreaterThan($budget, DB::table('sensors')->count(), 'test setup sanity check');

        $response = $this->withToken($this->token())
            ->getJson('/api/sensors/all/readings')
            ->assertOk();

        $sensors = $response->json('sensors');

        // The genuine global bound: the number of SENSOR objects returned must itself never
        // exceed the budget, independent of how many readings each one carries.
        $this->assertLessThanOrEqual(
            $budget,
            count($sensors),
            'sensor count in the response must be capped at the global row budget, not unbounded'
        );

        // Total rows (sensor rows + reading rows) must never exceed the budget either.
        $totalReadingRows = collect($sensors)->sum(fn ($s) => count($s['readings']));
        $this->assertLessThanOrEqual(
            $budget,
            count($sensors) + $totalReadingRows,
            'sensors + readings combined must respect the single global row ceiling'
        );

        // Truncation must be reported, not silent.
        $this->assertTrue($response->json('sensors_truncated'));
        $this->assertSame($totalSensors, $response->json('total_sensors'));
        $this->assertGreaterThan(0, $response->json('sensors_dropped_count'));
    }

    public function test_below_the_cap_no_truncation_is_reported_and_readings_are_not_starved(): void
    {
        $totalSensors = 10;
        $this->seedSensorsWithReadings($totalSensors, $totalSensors);

        $response = $this->withToken($this->token())
            ->getJson('/api/sensors/all/readings')
            ->assertOk();

        $this->assertFalse($response->json('sensors_truncated'));
        $this->assertSame(0, $response->json('sensors_dropped_count'));
        $this->assertSame($totalSensors, $response->json('total_sensors'));

        $sensors = $response->json('sensors');
        $this->assertCount($totalSensors, $sensors);
        foreach ($sensors as $sensor) {
            $this->assertGreaterThanOrEqual(1, count($sensor['readings']));
        }
    }
}
