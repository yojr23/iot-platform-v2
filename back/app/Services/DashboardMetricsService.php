<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Services\Alerts\AlertService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DashboardMetricsService
{
    public function __construct(private AlertService $alertService)
    {
    }

    public function getSummaryStats(): array
    {
        Log::info('DashboardMetricsService:getSummaryStats entry');

        $startTime = microtime(true);

        $cacheKey = 'dashboard:active_alerts_count';
        $cacheHit = Cache::has($cacheKey);
        Log::info('DashboardMetricsService:getSummaryStats cache check', ['cache_hit' => $cacheHit, 'key' => $cacheKey]);

        $activeAlerts = Cache::remember($cacheKey, 5, function (): int {
            return $this->alertService->getActiveAlertsCount();
        });

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $result = [
            'totalDevices' => Device::count(),
            'activeDevices' => Device::where('status', true)->count(),
            'activeAlerts' => $activeAlerts,
        ];

        Log::info('DashboardMetricsService:getSummaryStats completed', [
            'duration_ms' => $durationMs,
            'total_devices' => $result['totalDevices'],
            'active_devices' => $result['activeDevices'],
            'active_alerts' => $result['activeAlerts'],
        ]);

        if ($durationMs > 100) {
            Log::warning('DashboardMetricsService:getSummaryStats slow execution', ['duration_ms' => $durationMs]);
        }

        return $result;
    }

    public function getActiveAlertsList(int $limit = 10)
    {
        Log::info('DashboardMetricsService:getActiveAlertsList entry', ['limit' => $limit]);

        $startTime = microtime(true);
        $cacheKey = "dashboard:active_alerts_list:{$limit}";
        $cacheHit = Cache::has($cacheKey);
        Log::info('DashboardMetricsService:getActiveAlertsList cache check', ['cache_hit' => $cacheHit, 'key' => $cacheKey]);

        $result = Cache::remember($cacheKey, 5, function () use ($limit) {
            return $this->alertService->getActiveAlertsList($limit);
        });

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('DashboardMetricsService:getActiveAlertsList completed', [
            'duration_ms' => $durationMs,
            'cache_hit' => $cacheHit,
            'count' => $result->count(),
        ]);

        if ($durationMs > 100) {
            Log::warning('DashboardMetricsService:getActiveAlertsList slow execution', ['duration_ms' => $durationMs]);
        }

        return $result;
    }

    public function getDevicesForSelection()
    {
        Log::info('DashboardMetricsService:getDevicesForSelection entry');

        $startTime = microtime(true);
        $result = Device::with('lab')->orderBy('name')->get();
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DashboardMetricsService:getDevicesForSelection completed', [
            'duration_ms' => $durationMs,
            'count' => $result->count(),
        ]);

        if ($durationMs > 100) {
            Log::warning('DashboardMetricsService:getDevicesForSelection slow query', ['duration_ms' => $durationMs, 'table' => 'devices']);
        }

        return $result;
    }

    public function getSensorTypes()
    {
        Log::info('DashboardMetricsService:getSensorTypes entry');

        $startTime = microtime(true);
        $result = SensorType::orderBy('name')->get();
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DashboardMetricsService:getSensorTypes completed', [
            'duration_ms' => $durationMs,
            'count' => $result->count(),
        ]);

        if ($durationMs > 100) {
            Log::warning('DashboardMetricsService:getSensorTypes slow query', ['duration_ms' => $durationMs, 'table' => 'sensor_types']);
        }

        return $result;
    }

    public function getSensors()
    {
        Log::info('DashboardMetricsService:getSensors entry');

        $startTime = microtime(true);
        $result = Sensor::with('sensorType')->orderBy('name')->get();
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DashboardMetricsService:getSensors completed', [
            'duration_ms' => $durationMs,
            'count' => $result->count(),
        ]);

        if ($durationMs > 100) {
            Log::warning('DashboardMetricsService:getSensors slow query', ['duration_ms' => $durationMs, 'table' => 'sensors']);
        }

        return $result;
    }
}
