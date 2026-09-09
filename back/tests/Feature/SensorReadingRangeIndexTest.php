<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pre-Stage-6 Task 4 (PLAN.md graph-range prerequisite): bounded range queries on
 * sensor_readings (WHERE sensor_id = ? AND reading_time BETWEEN ? AND ? ORDER BY
 * reading_time, id) need a composite index with that exact column order as a prefix,
 * otherwise sqlite/MySQL fall back to a full table scan per sensor per graph render.
 * This asserts the real index metadata exists after migration, not just that a
 * migration file was added.
 */
class SensorReadingRangeIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_composite_range_index_exists_with_correct_column_order(): void
    {
        $indexes = collect(DB::select("PRAGMA index_list('sensor_readings')"));

        $target = $indexes->firstWhere('name', 'sensor_readings_sensor_time_id_idx');

        $this->assertNotNull(
            $target,
            'Expected index sensor_readings_sensor_time_id_idx to exist on sensor_readings.'
        );

        $columns = collect(DB::select("PRAGMA index_info('sensor_readings_sensor_time_id_idx')"))
            ->sortBy('seqno')
            ->pluck('name')
            ->values()
            ->all();

        $this->assertSame(
            ['sensor_id', 'reading_time', 'id'],
            $columns,
            'Composite index must cover (sensor_id, reading_time, id) in that exact order.'
        );
    }
}
