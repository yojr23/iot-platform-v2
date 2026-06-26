<?php

namespace App\Observers;

use App\Models\Alert;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Cache;

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
