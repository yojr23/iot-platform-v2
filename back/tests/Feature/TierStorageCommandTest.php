<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TierStorageCommandTest extends TestCase
{
    use RefreshDatabase;

    private function raw(string $status, int $ageDays): int
    {
        $at = now()->subDays($ageDays);

        return DB::table('raw_sensor_events')->insertGetId([
            'payload' => '{}', 'status' => $status, 'processed_at' => $status === 'processed' ? $at : null,
            'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function outbox(int $rawId): void
    {
        DB::table('raw_event_outboxes')->insert(['raw_sensor_event_id' => $rawId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function domain(string $status, bool $delivered, int $ageDays): void
    {
        $at = now()->subDays($ageDays);
        DB::table('domain_event_outboxes')->insert([
            'event_type' => 't', 'aggregate_type' => 'a', 'aggregate_id' => '1', 'payload' => '{}',
            'status' => $status, 'delivered_at' => $delivered ? $at : null, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function audit(int $ageDays): void
    {
        $at = now()->subDays($ageDays);
        DB::table('audit_logs')->insert(['action' => 'x', 'resource_type' => 'y', 'created_at' => $at, 'updated_at' => $at]);
    }

    public function test_moves_only_old_processed_raw_events_and_cascades_outbox(): void
    {
        $oldProcessed = [$this->raw('processed', 30), $this->raw('processed', 20)];
        $this->raw('processed', 1);
        $this->raw('failed', 100);
        $this->raw('received', 100);
        $this->raw('processing', 100);
        foreach ($oldProcessed as $id) {
            $this->outbox($id);
        }
        DB::table('reading_projections')->count(); // table exists; FK is nullOnDelete

        $this->artisan('iot:storage:tier')->assertSuccessful();

        $this->assertSame(4, DB::table('raw_sensor_events')->count());
        $this->assertSame(2, DB::table('raw_sensor_events_archive')->count());
        $this->assertSame(6, DB::table('raw_sensor_events')->count() + DB::table('raw_sensor_events_archive')->count());
        $this->assertEqualsCanonicalizing($oldProcessed, DB::table('raw_sensor_events_archive')->pluck('id')->all());
        $this->assertNotNull(DB::table('raw_sensor_events_archive')->value('archived_at'));
        $this->assertSame(0, DB::table('raw_event_outboxes')->count());
        $this->assertSame(['failed' => 1, 'processed' => 1, 'processing' => 1, 'received' => 1],
            DB::table('raw_sensor_events')->selectRaw('status, count(*) c')->groupBy('status')->orderBy('status')->pluck('c', 'status')->all());

        // second run is a no-op
        $this->artisan('iot:storage:tier')->assertSuccessful();
        $this->assertSame(4, DB::table('raw_sensor_events')->count());
        $this->assertSame(2, DB::table('raw_sensor_events_archive')->count());
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->raw('processed', 30);
        $this->audit(200);
        $this->domain('published', true, 30);

        $this->artisan('iot:storage:tier --dry-run')->assertSuccessful();

        $this->assertSame(1, DB::table('raw_sensor_events')->count());
        $this->assertSame(1, DB::table('audit_logs')->count());
        $this->assertSame(1, DB::table('domain_event_outboxes')->count());
        $this->assertSame(0, DB::table('raw_sensor_events_archive')->count());
        $this->assertSame(0, DB::table('audit_logs_archive')->count());
    }

    public function test_domain_outbox_pruning_respects_terminal_status(): void
    {
        $this->domain('published', true, 30);   // pruned
        $this->domain('published', false, 30);  // not delivered yet: kept
        $this->domain('pending', false, 30);    // kept
        $this->domain('publishing', true, 30);  // kept
        $this->domain('published', true, 1);    // too new: kept

        $this->artisan('iot:storage:tier')->assertSuccessful();

        $this->assertSame(4, DB::table('domain_event_outboxes')->count());
    }

    public function test_audit_logs_move_to_archive(): void
    {
        $this->audit(200);
        $this->audit(100);
        $this->audit(5);

        $this->artisan('iot:storage:tier')->assertSuccessful();

        $this->assertSame(1, DB::table('audit_logs')->count());
        $this->assertSame(2, DB::table('audit_logs_archive')->count());
    }
}
