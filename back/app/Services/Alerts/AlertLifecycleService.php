<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Services\Ingestion\DomainEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * PLAN.md Stage 4.2/4.3, G0D row B4 — the single alert *lifecycle transition* owner (resolve /
 * resolve-all). `App\Services\Alerts\AlertService` remains the sole rule-*evaluation* owner
 * (trigger scoping/threshold checks, G0D row B1) — this class never re-implements that and is
 * intentionally a separate class for a separate responsibility (PLAN.md 4.3: "evolve or add an
 * alert transition service only for lifecycle transitions").
 *
 * Existing code reused: `Alert::update()` (still fires `AlertObserver::updated()` for its existing
 * cache-invalidation compat behavior — no change there); `DomainEventRecorder` (Stage 4.1) for the
 * outbox write.
 * Existing owner retired/delegated: `Api\AlertController::resolve()/resolveAll()` and the Blade
 * `AlertController::resolve()/markAllAsResolved()` no longer mutate `Alert` rows themselves (audit
 * RC2: `resolveAll()` used a query-builder mass `update()` that bypassed `AlertObserver` and
 * emitted nothing) — both single and bulk resolution now go through this one class.
 * Compatibility window: none — one transition owner from this stage on.
 */
class AlertLifecycleService
{
    public function __construct(private DomainEventRecorder $recorder)
    {
    }

    public function resolve(Alert $alert): Alert
    {
        DB::transaction(function () use ($alert): void {
            $this->resolveWithinTransaction($alert);
        });

        return $alert;
    }

    /**
     * Bounded per-alert chunks (PLAN.md 4.2: "emits per-alert alert.resolved in bounded transaction
     * chunks") — never a single mass `update()` that skips the transition owner and the observer.
     *
     * @return int number of alerts actually resolved
     */
    public function resolveAll(int $chunkSize = 100): int
    {
        $ids = Alert::active()->orderBy('id')->pluck('id');
        $resolvedCount = 0;

        foreach ($ids->chunk($chunkSize) as $chunk) {
            $resolvedCount += DB::transaction(function () use ($chunk): int {
                $count = 0;

                $alerts = Alert::query()
                    ->whereIn('id', $chunk->all())
                    ->lockForUpdate()
                    ->get();

                foreach ($alerts as $alert) {
                    if ($alert->resolved) {
                        // Already resolved by a concurrent request between the pluck() above and
                        // this lock — skip rather than emit a duplicate alert.resolved fact.
                        continue;
                    }

                    $this->resolveWithinTransaction($alert);
                    $count++;
                }

                return $count;
            });
        }

        return $resolvedCount;
    }

    private function resolveWithinTransaction(Alert $alert): void
    {
        $alert->update([
            'resolved' => true,
            'resolved_at' => now(),
        ]);

        $this->recorder->record('alert.resolved', 'alert', $alert->id, [
            'alert_id' => $alert->id,
        ]);
    }
}
