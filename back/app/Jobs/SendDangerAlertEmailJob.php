<?php

namespace App\Jobs;

use App\Models\Alert;
use App\Services\Notifications\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * PLAN.md Stage 8.2 — takes the danger-alert email off the synchronous
 * reading -> observer -> alert request path (audit.md §13).
 *
 * Existing code reused: `NotificationService::notifyDangerAlertByEmail()` — the severity gate,
 * rate limiting and email payload mapping are unchanged; this job only relocates *when* that call
 * runs (queued worker instead of inline inside `AlertObserver::created()`). No new domain-outbox
 * event type was added: `DomainEventBroadcastConsumer`/`iot.domain-events` only cover
 * alert.resolved/device.status.changed/sensor.reading.created today, alert-triggered email has no
 * existing consumer to route through, and standing up a new consumer group for a single email send
 * is the exact over-engineering audit.md §15a already rejected for comparable cases — a plain
 * Laravel queued job (same mechanism as `RelayDomainOutboxJob`/`RelayRawOutboxJob`) is the smallest
 * fix that gets email off the request path.
 * Existing owner retired/delegated: `AlertObserver::created()` no longer calls
 * `NotificationService::notifyDangerAlertByEmail()` synchronously; it now dispatches this job
 * `afterCommit()`. `NotificationService` remains the sole owner of notification policy/mapping.
 * Compatibility window: none — `AlertObserver::created()` is the only dispatch site.
 */
class SendDangerAlertEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $alertId)
    {
    }

    public function handle(NotificationService $notificationService): void
    {
        $alert = Alert::query()->find($this->alertId);

        if (! $alert) {
            return;
        }

        $notificationService->notifyDangerAlertByEmail($alert);
    }
}
