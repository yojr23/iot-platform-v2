<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Services\DashboardMetricsService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private DashboardMetricsService $metrics)
    {
    }

    public function metrics(): JsonResponse
    {
        return response()->json($this->dashboardPayload());
    }

    public function publicData(): JsonResponse
    {
        return response()->json($this->dashboardPayload() + [
            'devices' => $this->publicDevices(),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function dashboardPayload(): array
    {
        $summary = $this->metrics->getSummaryStats();

        return [
            'total_devices' => Device::count(),
            'active_devices' => $summary['activeDevices'],
            'total_sensors' => Sensor::count(),
            'active_alerts' => $summary['activeAlerts'],
            'unresolved_alerts' => Alert::active()->count(),
            'latest_readings' => $this->latestReadings(),
            'system_status' => 'ok',
        ];
    }

    private function latestReadings(int $limit = 10)
    {
        return SensorReading::with('sensor.sensorType', 'sensor.device')
            ->where('reading_time', '<=', now())
            ->orderByDesc('reading_time')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (SensorReading $reading): array => [
                'id' => $reading->id,
                'value' => (float) $reading->value,
                'reading_time' => $reading->reading_time?->toIso8601String(),
                'sensor' => $reading->sensor ? [
                    'id' => $reading->sensor->id,
                    'name' => $reading->sensor->name,
                    'unit' => $reading->sensor->sensorType?->unit,
                ] : null,
                'device' => $reading->sensor?->device ? [
                    'id' => $reading->sensor->device->id,
                    'name' => $reading->sensor->device->name,
                ] : null,
            ])
            ->values();
    }

    private function publicDevices()
    {
        return Device::with(['lab', 'sensors.sensorType'])
            ->orderBy('name')
            ->get()
            ->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'status' => (bool) $device->status,
                'is_active' => (bool) $device->is_active,
                'last_communication' => $device->last_communication?->toIso8601String(),
                'lab' => $device->lab ? [
                    'id' => $device->lab->id,
                    'name' => $device->lab->name,
                    'area' => $device->lab->area,
                    'process_line' => $device->lab->process_line,
                ] : null,
                'sensors' => $device->sensors
                    ->sortBy('name')
                    ->map(fn (Sensor $sensor): array => [
                        'id' => $sensor->id,
                        'name' => $sensor->name,
                        'status' => (bool) $sensor->status,
                        'unit' => $sensor->sensorType?->unit,
                        'sensor_type' => $sensor->sensorType ? [
                            'id' => $sensor->sensorType->id,
                            'name' => $sensor->sensorType->name,
                            'unit' => $sensor->sensorType->unit,
                        ] : null,
                    ])
                    ->values(),
            ])
            ->values();
    }
}
