<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN.md Stage 6.0 — server-owned public graph boundary.
 *
 * Existing code reused: n/a (new column on the existing `sensors` table owned by
 * `App\Models\Sensor`).
 * Existing owner retired/delegated: n/a — no prior visibility flag existed; sensor.status/
 * device.status/device.is_active are operational fields and must never be read as this flag
 * (see App\Services\Monitoring\PublicGraphVisibility).
 * Compatibility window: n/a. Deliberately NOT backfilled true for any existing sensor — every
 * sensor starts restricted until an admin explicitly opts it in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->boolean('public_monitoring_enabled')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sensors', function (Blueprint $table) {
            $table->dropColumn('public_monitoring_enabled');
        });
    }
};
