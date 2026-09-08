<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN.md Stage 2.5 / docs/implementation/adr-g1.md ADR-1: DB backstop for the check-then-create
 * race in App\Services\Alerts\AlertService::createAlertsForReading() (`:60-62`), which has no
 * unique constraint today.
 *
 * Existing code reused: `alerts` table (2025_04_29_134330_create_alerts_table.php).
 * Existing owner retired/delegated: none. AlertService keeps owning rule evaluation/creation
 * (G0D row B1, REUSE/EXTEND) — this migration only adds the schema-level backstop; the service's
 * check-then-create logic is intentionally left unchanged in this stage.
 * Compatibility window: none needed — a reading can only legitimately trigger a given alert rule
 * once, so this constraint should never reject a row from correct application code today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->unique(['sensor_reading_id', 'alert_rule_id'], 'alerts_reading_rule_unique');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropUnique('alerts_reading_rule_unique');
        });
    }
};
