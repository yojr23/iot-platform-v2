<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Alert;
use App\Models\SensorType;
use App\Models\SensorReading; // Importar SensorReading
use App\Services\DashboardMetricsService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardMetricsService $metrics)
    {
    }

    public function index()
    {
        $summary = $this->metrics->getSummaryStats();

        $activeAlertsList = $this->metrics->getActiveAlertsList();

        $devices = $this->metrics->getDevicesForSelection();
        $sensorTypes = $this->metrics->getSensorTypes();
        $sensors = $this->metrics->getSensors();

        return view('dashboard', compact(
            'summary',
            'activeAlertsList',
            'devices',
            'sensorTypes',
            'sensors'
        ));
    }
    
    public function getSensors(Device $device)
    {
        return response()->json($device->sensors()->with('sensorType')->get());
    }

    public function getSensorReadings($sensorId)
    {
        $sensor = \App\Models\Sensor::findOrFail($sensorId);
        $readings = $sensor->readings()->orderBy('reading_time', 'desc')->limit(100)->get();

        return response()->json($readings);
    }

    public function getActiveAlerts()
    {
        $summary = $this->metrics->getSummaryStats();

        return response()->json([
            'count' => $summary['activeAlerts'],
            'alerts' => $this->metrics->getActiveAlertsList()
        ]);
    }
}
