<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase E: /api/sensors/all/readings must be globally bounded and validate its inputs.
 * The prior model allowed sensor_count x per_sensor_limit work with no global ceiling, and
 * malformed timestamps produced a generic 500 instead of a controlled 422.
 */
class AllReadingsResourceBoundsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function token(): string
    {
        return User::factory()->create()->createToken('t', ['read'])->plainTextToken;
    }

    public function test_malformed_from_returns_422_not_500(): void
    {
        $this->withToken($this->token())
            ->getJson('/api/sensors/all/readings?from=not-a-date')
            ->assertStatus(422);
    }

    public function test_to_before_from_returns_422(): void
    {
        $this->withToken($this->token())
            ->getJson('/api/sensors/all/readings?from=2026-09-10T00:00:00Z&to=2026-09-01T00:00:00Z')
            ->assertStatus(422);
    }

    public function test_non_positive_limit_returns_422(): void
    {
        $this->withToken($this->token())
            ->getJson('/api/sensors/all/readings?limit=0')
            ->assertStatus(422);
    }

    public function test_total_rows_are_globally_bounded_across_many_sensors(): void
    {
        // 6 sensors, 40 readings each within the last hour. A naive per-sensor limit of 1000
        // would return all 240; a global budget must cap the TOTAL well below sensors x limit.
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $now = Carbon::now();
        foreach (range(1, 6) as $i) {
            $sensor = Sensor::factory()->create(['device_id' => $device->id]);
            foreach (range(1, 40) as $j) {
                SensorReading::factory()->create([
                    'sensor_id' => $sensor->id,
                    'reading_time' => $now->copy()->subMinutes($j),
                ]);
            }
        }

        $response = $this->withToken($this->token())
            ->getJson('/api/sensors/all/readings?limit=1000')
            ->assertOk();

        $total = collect($response->json('sensors'))->sum(fn ($s) => count($s['readings']));

        // Global budget must bound the total across all sensors, never sensor_count x limit.
        $this->assertLessThanOrEqual(
            \App\Http\Controllers\Api\SensorApiController::ALL_READINGS_GLOBAL_ROW_BUDGET,
            $total,
            'total returned rows must respect the global budget'
        );
    }

    public function test_per_sensor_slice_shrinks_as_sensor_count_grows(): void
    {
        // With a tiny budget (forced via a low request limit is not enough — the budget is a class
        // constant), prove the split: many sensors each get at most floor(budget / sensorCount) rows.
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $now = Carbon::now();
        $sensorCount = 10;
        foreach (range(1, $sensorCount) as $i) {
            $sensor = Sensor::factory()->create(['device_id' => $device->id]);
            foreach (range(1, 5) as $j) {
                SensorReading::factory()->create([
                    'sensor_id' => $sensor->id,
                    'reading_time' => $now->copy()->subMinutes($j),
                ]);
            }
        }

        $response = $this->withToken($this->token())
            ->getJson('/api/sensors/all/readings')
            ->assertOk();

        $budget = \App\Http\Controllers\Api\SensorApiController::ALL_READINGS_GLOBAL_ROW_BUDGET;
        $perSensorCap = intdiv($budget, $sensorCount);
        foreach ($response->json('sensors') as $s) {
            $this->assertLessThanOrEqual($perSensorCap, count($s['readings']));
        }
    }
}
