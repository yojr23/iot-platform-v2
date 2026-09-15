<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_sensor_mappings', function (Blueprint $table) {
            // Temporal lookup index: find the active mapping for a key at a given event time
            $table->index(
                ['device_id', 'source', 'external_key', 'is_active', 'valid_from', 'valid_until'],
                'dsm_temporal_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('device_sensor_mappings', function (Blueprint $table) {
            $table->dropIndex('dsm_temporal_lookup_idx');
        });
    }
};
