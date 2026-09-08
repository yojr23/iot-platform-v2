<?php

namespace App\Observers;

use App\Models\Alert;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Cache;

/**
 * PLAN.md Stage 4.3 (G0D row B3) scope note: this observer still owns the `alert.triggered`
 * synchronous broadcast + email on `created()` — that pair was already a single, non-duplicated
 * side effect (audit did not flag it as missing, unlike resolve/device-status), so Stage 4 leaves
 * it as-is rather than widening this change to the reading->alert creation transaction boundary.
 *
 * ponytail: `alert.triggered` is NOT yet routed through the domain outbox / `browser-delivery-v1`
 * consumer the way `alert.resolved` and `device.status.changed` now are (PLAN.md 4.1/4.2/4.4) — it
 * stays `ShouldBroadcastNow` on the request path. Ceiling: this is the one remaining synchronous
 * broadcast in the alert lifecycle. Upgrade path: move `AlertService::createAlertsForReading()`'s
 * `Alert::create()` into a DB transaction that also writes an `alert.triggered` domain-outbox row
 * (mirroring `AlertLifecycleService::resolveWithinTransaction()`), then drop `broadcastNewAlert()`
 * here — do this only alongside Stage 8's email-off-the-sync-path work, since both touch the same
 * `created()` hook and should not be split across two half-migrations.
 * `notifyDangerAlertByEmail()` staying synchronous here is intentional and out of scope for Stage 4
 * (PLAN.md Stage 8.2 owns moving email off the sync path).
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
        $this->notificationService->notifyDangerAlertByEmail($alert);
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
