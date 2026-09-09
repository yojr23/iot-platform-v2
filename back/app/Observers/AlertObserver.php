<?php

namespace App\Observers;

use App\Jobs\SendDangerAlertEmailJob;
use App\Models\Alert;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Cache;

/**
 * PLAN.md Stage 4.3 (G0D row B3) scope note: this observer still owns the `alert.triggered`
 * synchronous broadcast on `created()` — that side effect was already a single, non-duplicated
 * one (audit did not flag it as missing, unlike resolve/device-status), so Stage 4 left it as-is
 * rather than widening the change to the reading->alert creation transaction boundary.
 *
 * ponytail: `alert.triggered` is NOT yet routed through the domain outbox / `browser-delivery-v1`
 * consumer the way `alert.resolved` and `device.status.changed` now are (PLAN.md 4.1/4.2/4.4) — it
 * stays `ShouldBroadcastNow` on the request path. Ceiling: this is the one remaining synchronous
 * broadcast in the alert lifecycle. Upgrade path: move `AlertService::createAlertsForReading()`'s
 * `Alert::create()` into a DB transaction that also writes an `alert.triggered` domain-outbox row
 * (mirroring `AlertLifecycleService::resolveWithinTransaction()`), then drop `broadcastNewAlert()`
 * here. Not done as part of Stage 8.2: that stage only owned moving email off the sync path, not
 * the broadcast.
 *
 * PLAN.md Stage 8.2: `notifyDangerAlertByEmail()` no longer runs inline here. It is dispatched via
 * `SendDangerAlertEmailJob` (`afterCommit()`) so an unavailable/slow SMTP destination cannot delay
 * the ingestion response. `NotificationService::notifyDangerAlertByEmail()` itself is unchanged —
 * this observer is no longer its caller, the job is.
 */
class AlertObserver
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    public function created(Alert $alert): void
    {
        $this->clearDashboardAlertCaches();
        $this->notificationService->broadcastNewAlert($alert);
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
