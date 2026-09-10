<?php

namespace App\Services\Ingestion;

use App\Models\DomainEventOutbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PLAN.md Stage 4.1 / docs/implementation/adr-g1.md ADR-1.
 *
 * Existing code reused: this class is a line-by-line mirror of
 * `App\Services\Ingestion\RawOutboxRelay` (Stage 3) applied to `DomainEventOutbox` instead of
 * `RawEventOutbox` — same claim-lease-publish state machine, same anti-stranding discovery
 * rationale. No second outbox-relay design was invented.
 * Existing owner retired/delegated: n/a — domain events had no relay before this stage.
 * Compatibility window: none.
 *
 * Called from both `RelayDomainOutboxJob` (queued, `afterCommit()` low-latency wake-up hint) and
 * the `domain:relay-outbox` artisan command (durable discovery/recovery loop), exactly like the
 * raw pipeline, for the same reason: a process can die after DB commit and before the job is
 * dispatched, so `afterCommit()` alone is not a durable relay (PLAN.md, ADR-1).
 */
class DomainOutboxRelay
{
    // ponytail: fixed lease window, same as RawOutboxRelay — promote to config if operators need
    // to tune it per deployment independently of the raw pipeline's lease.
    private const LEASE_SECONDS = 30;

    public function __construct(private DomainEventPublisher $publisher)
    {
    }

    /**
     * @return array{claimed:int,published:int,failed:int}
     */
    public function relayPending(int $limit = 100): array
    {
        Log::info('DomainOutboxRelay:relayPending entry', ['limit' => $limit]);

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

        Log::info('DomainOutboxRelay:relayPending completed', array_merge($result, ['duration_ms' => $durationMs]));

        if ($durationMs > 100) {
            Log::warning('DomainOutboxRelay:relayPending slow execution', ['duration_ms' => $durationMs]);
        }

        return $result;
    }

    private function claimBatch(int $limit): Collection
    {
        return DB::transaction(function () use ($limit) {
            $now = Carbon::now();

            $ids = DomainEventOutbox::query()
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

            // Bulk update is intentional: DomainEventOutbox has no observers, so the "bulk update
            // doesn't fire observers" caveat does not apply to this transition.
            DomainEventOutbox::query()->whereIn('id', $ids)->update([
                'status' => 'publishing',
                'locked_until' => $now->copy()->addSeconds(self::LEASE_SECONDS),
                'updated_at' => $now,
            ]);

            return DomainEventOutbox::query()->whereIn('id', $ids)->get();
        });
    }

    private function publishOne(DomainEventOutbox $outbox): bool
    {
        // Same accepted at-least-once note as RawOutboxRelay: a crash between publish() succeeding
        // and this update() committing can cause a re-claim-and-republish after lease expiry.
        // Dedup is the domain stream consumer's job (delivered_at on this same row).
        $published = $this->publisher->publish($outbox);

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

        Log::warning('DomainOutboxRelay: publish failed, left pending for retry', [
            'outbox_id' => $outbox->id,
            'event_type' => $outbox->event_type,
        ]);

        return false;
    }
}
