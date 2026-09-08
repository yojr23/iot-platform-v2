<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN.md Stage 2.4/2.5 (docs/implementation/adr-g1.md ADR-1 "Idempotency at schema level").
 *
 * Existing code reused: `raw_sensor_events` table (2026_05_14_190000_create_raw_sensor_events_table.php).
 * Existing owner retired/delegated: none — this is an additive column + index, no owner changes.
 * Compatibility window: `source_event_id` is nullable so producers that don't yet send it keep
 * working; rows with a null `source_event_id` are exempt from the unique constraint (both MySQL
 * and SQLite treat NULL as distinct in unique indexes), so the backstop only activates once a
 * producer actually supplies the identity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_sensor_events', function (Blueprint $table): void {
            $table->string('source_event_id')->nullable()->after('source');
        });

        Schema::table('raw_sensor_events', function (Blueprint $table): void {
            $table->unique(['source', 'source_event_id'], 'raw_sensor_events_source_source_event_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('raw_sensor_events', function (Blueprint $table): void {
            $table->dropUnique('raw_sensor_events_source_source_event_id_unique');
            $table->dropColumn('source_event_id');
        });
    }
};
