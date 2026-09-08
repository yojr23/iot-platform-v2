<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN.md Stage 3.1 / docs/implementation/adr-g1.md ADR-1 (transactional outbox + Laravel queue
 * relay with a durable committed-outbox discovery loop).
 *
 * Existing code reused: `raw_sensor_events` table/model (business row); `RawSensorEventPublisher`
 * (low-level XADD transport, unchanged) is the thing this table's rows get relayed through.
 * Existing owner retired/delegated: `IngestionController` no longer calls the publisher
 * synchronously on the request path (see G0D row B6) — this table is the new durable record of
 * "needs to be published" that replaces that synchronous call.
 * Compatibility window: none needed — one outbox row per raw_sensor_events row, created in the
 * same DB transaction as the business row from this migration forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_event_outboxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('raw_sensor_event_id')
                ->unique()
                ->constrained('raw_sensor_events')
                ->cascadeOnDelete();
            // pending -> publishing (leased) -> published | (back to pending on failure, retried)
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'locked_until'], 'raw_event_outboxes_status_locked_until_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_event_outboxes');
    }
};
