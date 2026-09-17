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
 * PENDING MAC VERIFICATION — written on Windows, never executed here.
 *
 * Fix 2 root-cause regression (task item 4): with MORE sensors than
 * `SensorApiController::ALL_READINGS_GLOBAL_ROW_BUDGET` in the database, `allReadings()` must
 * never hydrate more sensor rows than the budget — the exact bug this fix closes. Before the fix,
 * `Sensor::with(...)->get()` had no `->limit()` at all: with 6,000 real sensors the endpoint
 * loaded all 6,000 sensor objects (unbounded) regardless of the budget constant. Uses a raw bulk
 * `DB::table('sensors')->insert()` (bypassing Eloquent/observers) to create sensor rows above the
 * ceiling quickly — this test only needs row COUNT, not full model behavior.
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

    public function test_sensor_count_in_response_never_exceeds_the_global_budget_with_more_sensors_than_the_ceiling(): void
    {
        $budget = SensorApiController::ALL_READINGS_GLOBAL_ROW_BUDGET;
        $totalSensors = $budget + 500; // deliberately above the ceiling

        $device = Device::factory()->create();
        $sensorType = SensorType::factory()->create();
        $now = now();

        // Raw bulk insert (bypasses Eloquent/observers) — this test only needs row COUNT to exist,
        // not full model lifecycle, so a fast chunked insert keeps this test practical to run.
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
    }
}
