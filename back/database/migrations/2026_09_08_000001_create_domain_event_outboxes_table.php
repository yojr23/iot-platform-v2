<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN.md Stage 4.1 / docs/implementation/adr-g1.md ADR-1 (same transactional-outbox + queue-relay
 * pattern Stage 3 built for `raw_event_outboxes`, applied to domain facts).
 *
 * Existing code reused: the whole shape mirrors `2026_09_07_000003_create_raw_event_outbox_table`
 * (status lifecycle, lease column, attempts/last_error/published_at) — no new outbox design.
 * Existing owner retired/delegated: n/a — no domain-event durability existed before this stage
 * (`NewAlertTriggered`/`DeviceStatusUpdated` broadcast, or didn't dispatch at all, with no durable
 * record either way).
 * Compatibility window: none — one outbox row per emitted domain fact from this stage on.
 *
 * `delivered_at` is this table's own addition versus the raw outbox: unlike `raw_sensor_events`
 * (which has its own `status` column doubling as the raw consumer's idempotency ledger), a domain
 * outbox row's `status` column is already spent on the *relay's* pending/publishing/published
 * lifecycle. `delivered_at` is the separate marker the `browser-delivery-v1` stream consumer uses
 * to dedupe redelivery, scoped to this one consumer group.
 *
 * ponytail: a single `delivered_at` column (not a `(consumer_group, event_id)` ledger table) is
 * only correct because Stage 4 stands up exactly one consumer group. PLAN.md Stage 8 gates
 * `email-delivery-v1`/`webhook-delivery-v1` on a real destination being approved — when that
 * happens, replace this column with a per-consumer-group delivery ledger table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_event_outboxes', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type');
            $table->string('aggregate_type');
            $table->string('aggregate_id');
            $table->json('payload');
            // pending -> publishing (leased) -> published | (back to pending on failure, retried)
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'locked_until'], 'domain_event_outboxes_status_locked_until_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_event_outboxes');
    }
};
