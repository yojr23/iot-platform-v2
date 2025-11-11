<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;

class DashboardMetricsService
{
    public function getSummaryStats(): array
    {
        return [
            'totalDevices' => Device::count(),
            'activeDevices' => Device::where('status', true)->count(),
            'activeAlerts' => Alert::where('resolved', false)->count(),
        ];
    }

    public function getActiveAlertsList(int $limit = 10)
    {
        return Alert::with([
                'sensorReading.sensor.sensorType',
                'sensorReading.sensor.device.classroom',
                'alertRule',
            ])
            ->where('resolved', false)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getDevicesForSelection()
    {
        return Device::with('classroom')
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
