<?php

namespace App\Observers;

use App\Jobs\SendDangerAlertEmailJob;
use App\Models\Alert;
use Illuminate\Support\Facades\Cache;

/**
 * PLAN.md Stage 7.5 (G0D row B3): `alert.triggered` no longer broadcasts synchronously here.
 * `App\Services\Alerts\AlertService::createAlertsForReading()` now writes the `alert.triggered`
 * domain-outbox row in the same transaction as `Alert::create()` (mirroring
 * `AlertLifecycleService::resolveWithinTransaction()` for `alert.resolved`), and
 * `App\Services\Ingestion\DomainEventBroadcastConsumer` is the sole dispatcher of
 * `NewAlertTriggered`, same as `AlertResolved`. This observer keeps only cache invalidation and the
 * afterCommit email dispatch — it is no longer a broadcast owner.
 *
 * Existing code reused: `NotificationService::notifyDangerAlertByEmail()` behavior is unchanged
 * (still reached only via `SendDangerAlertEmailJob`, Stage 8.2).
 * Existing owner retired/delegated: `NotificationService::broadcastNewAlert()` is now dead code from
 * this observer's perspective — no caller left in the created() path.
 * Compatibility window: none.
 *
 * PLAN.md Stage 8.2: `notifyDangerAlertByEmail()` no longer runs inline here. It is dispatched via
 * `SendDangerAlertEmailJob` (`afterCommit()`) so an unavailable/slow SMTP destination cannot delay
 * the ingestion response. `NotificationService::notifyDangerAlertByEmail()` itself is unchanged —
 * this observer is no longer its caller, the job is.
 */
class AlertObserver
{
    public function created(Alert $alert): void
    {
        $this->clearDashboardAlertCaches();
        SendDangerAlertEmailJob::dispatch($alert->id)->afterCommit();
    }

    public function updated(Alert $alert): void
    {
        // Resolve transitions are now owned by App\Services\Alerts\AlertLifecycleService, which
        // still goes through Alert::update() (so this hook still fires) but also writes the
        // alert.resolved domain-outbox row itself. This stays cache-invalidation-only so it never
        // competes with that new outbox write (PLAN.md 4.3: observers must not double-cover a
        // transition a new owner already handles).
        if ($alert->isDirty(['resolved', 'resolved_at'])) {
            $this->clearDashboardAlertCaches();
        }
    }

    private function clearDashboardAlertCaches(): void
    {
        Cache::forget('dashboard:active_alerts_count');
        Cache::forget('dashboard:active_alerts_list:10');
        Cache::forget('dashboard:active_alerts_list:20');
    }
}
