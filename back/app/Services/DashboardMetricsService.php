<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Services\Alerts\AlertService;
use Illuminate\Support\Facades\Cache;

class DashboardMetricsService
{
    public function __construct(private AlertService $alertService)
    {
    }

    public function getSummaryStats(): array
    {
        $activeAlerts = Cache::remember('dashboard:active_alerts_count', 5, function (): int {
            return $this->alertService->getActiveAlertsCount();
        });

        return [
            'totalDevices' => Device::count(),
            'activeDevices' => Device::where('status', true)->count(),
            'activeAlerts' => $activeAlerts,
        ];
    }

    public function getActiveAlertsList(int $limit = 10)
    {
        return Cache::remember("dashboard:active_alerts_list:{$limit}", 5, function () use ($limit) {
            return $this->alertService->getActiveAlertsList($limit);
        });
    }

    public function getDevicesForSelection()
    {
        return Device::with('lab')
            ->orderBy('name')
            ->get();
    }

    public function getSensorTypes()
    {
        return SensorType::orderBy('name')->get();
    }

    public function getSensors()
    {
        return Sensor::with('sensorType')
            ->orderBy('name')
            ->get();
    }
}
