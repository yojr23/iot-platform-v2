<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill device_sensor_mappings for all existing sensors that have no mapping yet.
        // external_key = lowercased sensor name (matches what MQTT payloads send as keys).
        // source = 'ingestion_service' (matches RawReadingNormalizer default).
        DB::statement('
            INSERT IGNORE INTO device_sensor_mappings
                (device_id, sensor_id, source, external_key, is_active, valid_from, valid_until, metadata, created_at, updated_at)
            SELECT
                s.device_id,
                s.id,
                \'ingestion_service\',
                LOWER(TRIM(s.name)),
                1,
                s.created_at,
                NULL,
                NULL,
                NOW(),
                NOW()
            FROM sensors s
            WHERE NOT EXISTS (
                SELECT 1 FROM device_sensor_mappings dsm
                WHERE dsm.sensor_id = s.id
                  AND dsm.source = \'ingestion_service\'
            )
        ');
    }

    public function down(): void
    {
        // Mapping rows are data, not schema — do not delete on rollback.
    }
};
