<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill device_sensor_mappings for every existing sensor that has no
     * ingestion_service mapping yet.
     *
     *   external_key = lower(trim(sensor.name))  — matches MQTT payload keys
     *   source       = 'ingestion_service'       — matches RawReadingNormalizer default
     *
     * Portable across SQLite (CI) and MySQL: no INSERT IGNORE / NOW() raw SQL.
     *
     * Fail-closed: if two sensors under the same device canonicalize to the same
     * external_key, the ingestion identity (device_id, source, external_key) is
     * ambiguous. We abort rather than silently pick one sensor — an operator must
     * disambiguate before this cutover can proceed.
     */
    public function up(): void
    {
        $now = now();

        // Sensors already mapped for this source must not be touched.
        $mappedSensorIds = DB::table('device_sensor_mappings')
            ->where('source', 'ingestion_service')
            ->pluck('sensor_id')
            ->all();

        $sensors = DB::table('sensors')
            ->select('id', 'device_id', 'name', 'created_at')
            ->when($mappedSensorIds, fn ($q) => $q->whereNotIn('id', $mappedSensorIds))
            ->get();

        // Preflight: detect ambiguous (device_id, canonical external_key) identities.
        $seen = [];
        $collisions = [];
        foreach ($sensors as $sensor) {
            $key = mb_strtolower(trim((string) $sensor->name));
            $identity = $sensor->device_id . '|' . $key;
            if (isset($seen[$identity])) {
                $collisions[$identity] = true;
            }
            $seen[$identity] = true;
        }

        if ($collisions) {
            $sample = implode(', ', array_slice(array_keys($collisions), 0, 10));
            throw new RuntimeException(
                'Backfill aborted: ambiguous (device_id, ingestion_service, external_key) identities '
                . 'detected — multiple sensors canonicalize to the same key. An operator must resolve '
                . 'these before migrating. Colliding device_id|external_key: ' . $sample
            );
        }

        $rows = [];
        foreach ($sensors as $sensor) {
            $rows[] = [
                'device_id'   => $sensor->device_id,
                'sensor_id'   => $sensor->id,
                'source'      => 'ingestion_service',
                'external_key' => mb_strtolower(trim((string) $sensor->name)),
                'is_active'   => 1,
                'valid_from'  => $sensor->created_at,
                'valid_until' => null,
                'metadata'    => null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('device_sensor_mappings')->insert($chunk);
        }
    }

    public function down(): void
    {
        // Mapping rows are data, not schema — do not delete on rollback.
    }
};
