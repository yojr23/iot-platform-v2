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
        $columns = DB::getDriverName() === 'mysql'
            ? $this->mysqlIndexColumns('sensor_readings', 'sensor_readings_sensor_time_id_idx')
            : $this->sqliteIndexColumns('sensor_readings_sensor_time_id_idx');

        $this->assertNotEmpty(
            $columns,
            'Expected index sensor_readings_sensor_time_id_idx to exist on sensor_readings.'
        );

        $this->assertSame(
            ['sensor_id', 'reading_time', 'id'],
            $columns,
            'Composite index must cover (sensor_id, reading_time, id) in that exact order.'
        );
    }

    /** @return array<int,string> */
    private function sqliteIndexColumns(string $index): array
    {
        $exists = collect(DB::select("PRAGMA index_list('sensor_readings')"))
            ->firstWhere('name', $index);

        if ($exists === null) {
            return [];
        }

        return collect(DB::select("PRAGMA index_info('{$index}')"))
            ->sortBy('seqno')
            ->pluck('name')
            ->values()
            ->all();
    }

    /** @return array<int,string> */
    private function mysqlIndexColumns(string $table, string $index): array
    {
        return collect(DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]))
            ->sortBy('Seq_in_index')
            ->pluck('Column_name')
            ->values()
            ->all();
    }
}
