<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // W-DB-01: drop plaintext api_key. Migration 000006 already backfilled
        // api_key_hash + api_key_prefix for all existing rows. New Device::creating
        // writes only hash/prefix/timestamp — plaintext is never stored.
        if (! Schema::hasColumn('devices', 'api_key')) {
            return;
        }

        // The plaintext column was created with ->unique() (index devices_api_key_unique).
        // SQLite refuses to drop a column still referenced by an index, so drop the
        // index first. MySQL tolerates dropping either order; doing it explicitly keeps
        // the migration portable across both drivers (CI runs on SQLite).
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique('devices_api_key_unique');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('api_key');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('api_key')->nullable()->after('mac_address');
        });
    }
};
