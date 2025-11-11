<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;

class DeviceApiController extends Controller
{
    public function index()
    {
        $devices = Device::with(['deviceType', 'classroom', 'sensors.sensorType'])->get();
        return response()->json($devices);
    }

    public function show(Device $device)
    {
        $device->load(['deviceType', 'classroom', 'sensors.sensorType', 'sensors.readings' => function($query) {
            $query->orderBy('reading_time', 'desc')->limit(100);
        }]);
        
        return response()->json($device);
    }

    public function updateStatus(Request $request, Device $device)
    {
        $request->validate([
            'status' => 'required|boolean'
        ]);

        $device->update(['status' => $request->status]);
        
        return response()->json([
            'message' => 'Estado del dispositivo actualizado',
            'device' => $device
        ]);
    }
}   