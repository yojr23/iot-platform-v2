<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The original unique key allowed only one row for a stable external identity. A
        // temporal mapping retains prior intervals, so uniqueness belongs to the interval
        // rules enforced by SensorMappingService, not to this three-column identity.
        //
        // On MySQL the devices FK (device_id) uses the (device_id, source, external_key) unique
        // key as its supporting index, so that index cannot be dropped while it is the only
        // device_id-leftmost index. Build the replacement temporal index FIRST (it also starts
        // with device_id and can back the FK), then drop the old temporal index and the unique
        // key. Use a distinct temporary name so we never briefly hold two indexes of one name.
        Schema::table('device_sensor_mappings', function (Blueprint $table) {
            $table->index(
                ['device_id', 'source', 'external_key', 'valid_from', 'valid_until'],
                'dsm_temporal_lookup_idx_v2',
            );
        });

        Schema::table('device_sensor_mappings', function (Blueprint $table) {
            $table->dropIndex('dsm_temporal_lookup_idx');
            $table->dropUnique('device_sensor_mappings_device_id_source_external_key_unique');
        });

        // Rename the replacement into the canonical name the rest of the schema expects.
        Schema::table('device_sensor_mappings', function (Blueprint $table) {
            $table->renameIndex('dsm_temporal_lookup_idx_v2', 'dsm_temporal_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('device_sensor_mappings', function (Blueprint $table) {
            $table->dropIndex('dsm_temporal_lookup_idx');
            // This migration is semantically irreversible: temporal history can contain
            // multiple intervals for one external identity, so restoring the original
            // three-column unique key would fail without discarding that history.
            $table->index(
                ['device_id', 'source', 'external_key', 'is_active', 'valid_from', 'valid_until'],
                'dsm_temporal_lookup_idx',
            );
        });
    }
};
