<?php

namespace App\Services\Ingestion;

use App\Models\RawEventOutbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PLAN.md Stage 3.2 / docs/implementation/adr-g1.md ADR-1.
 *
 * Existing code reused: `RawSensorEventPublisher::publish()` — kept as-is, this class only decides
 * *when* to call it and records the outcome. `RawEventOutbox` (this stage) is the durable work
 * table; no CDC/binlog connector was introduced (rejected in ADR-1).
 * Existing owner retired/delegated: `IngestionController` no longer calls the publisher directly
 * (G0D row B6) — this relay is the sole caller from here forward.
 * Compatibility window: none — single relay implementation from this stage on.
 *
 * Called from two places that both do the same claim-and-publish work, by design (no duplicated
 * logic): `RelayRawOutboxJob` (queued, `afterCommit()` low-latency wake-up hint) and the
 * `ingestion:relay-outbox` artisan command (durable discovery/recovery loop, required by ADR-1 so
 * a process death between commit and job dispatch never strands a row).
 */
class RawOutboxRelay
{
    /**
     * ponytail: fixed lease window rather than a config knob — ADR-1 only requires it to exceed
     * max transaction duration. Promote to config if operators need to tune it per deployment.
     */
    private const LEASE_SECONDS = 30;

    public function __construct(private RawSensorEventPublisher $publisher)
    {
    }

    /**
     * Claim up to $limit pending/lease-expired outbox rows and attempt to publish each.
     *
     * @return array{claimed:int,published:int,failed:int}
     */
    public function relayPending(int $limit = 100): array
    {
        Log::info('RawOutboxRelay:relayPending entry', ['limit' => $limit]);

        $startTime = microtime(true);
        $claimed = $this->claimBatch($limit);

        $published = 0;
        $failed = 0;

        foreach ($claimed as $outbox) {
            if ($this->publishOne($outbox)) {
                $published++;
            } else {
                $failed++;
            }
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $result = [
            'claimed' => $claimed->count(),
            'published' => $published,
            'failed' => $failed,
        ];

        Log::info('RawOutboxRelay:relayPending completed', array_merge($result, ['duration_ms' => $durationMs]));

        if ($durationMs > 100) {
            Log::warning('RawOutboxRelay:relayPending slow execution', ['duration_ms' => $durationMs]);
        }

        return $result;
    }

    /**
     * Bounded, restart-recovery claim of the durable outbox work table (PLAN.md Stage 3.2 /
     * ADR-1 "anti-stranding mechanism"). This is not periodic realtime-state polling: it is a
     * lease-based claim over a durable queue table, triggered by worker lifecycle + the
     * afterCommit() wake-up hint.
     */
    private function claimBatch(int $limit): Collection
    {
        return DB::transaction(function () use ($limit) {
            $now = Carbon::now();

            $ids = RawEventOutbox::query()
                ->where(function ($query) use ($now): void {
                    $query->where('status', 'pending')
                        ->orWhere(function ($query2) use ($now): void {
                            $query2->where('status', 'publishing')
                                ->where('locked_until', '<', $now);
                        });
                })
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->pluck('id');

            if ($ids->isEmpty()) {
                return collect();
            }

            // Bulk update is intentional here: RawEventOutbox has no observers, so the
            // "bulk update doesn't fire observers" caveat does not apply to this transition.
            RawEventOutbox::query()->whereIn('id', $ids)->update([
                'status' => 'publishing',
                'locked_until' => $now->copy()->addSeconds(self::LEASE_SECONDS),
                'updated_at' => $now,
            ]);

            return RawEventOutbox::query()->with('rawSensorEvent')->whereIn('id', $ids)->get();
        });
    }

    private function publishOne(RawEventOutbox $outbox): bool
    {
        $event = $outbox->rawSensorEvent;

        if (! $event) {
            Log::error('RawOutboxRelay: outbox row has no matching raw_sensor_event', [
                'outbox_id' => $outbox->id,
            ]);

            $outbox->update([
                'status' => 'failed',
                'last_error' => 'raw_sensor_event missing',
                'locked_until' => null,
            ]);

            return false;
        }

        // Note (ADR-1 spike finding #2): if this process dies after publish() succeeds but before
        // the update() below commits, the row can be re-claimed after lease expiry and published
        // again. That is accepted at-least-once behaviour, not a bug — dedup is the raw consumer's
        // job (RawStreamConsumer dedupes by `raw_sensor_events.status`).
        $published = $this->publisher->publish($event);

        if ($published) {
            $outbox->update([
                'status' => 'published',
                'published_at' => Carbon::now(),
                'locked_until' => null,
            ]);

            return true;
        }

        $outbox->update([
            'status' => 'pending',
            'attempts' => $outbox->attempts + 1,
            'last_error' => 'publish() returned false (Redis unavailable or publish failed)',
            'locked_until' => null,
        ]);

        return false;
    }
}
