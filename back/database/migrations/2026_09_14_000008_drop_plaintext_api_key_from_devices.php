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
