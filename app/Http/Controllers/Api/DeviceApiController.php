<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;

class DeviceApiController extends Controller
{
    public function index()
    {
        $perPage = (int) request()->query('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $devices = Device::with(['deviceType', 'lab', 'sensors.sensorType'])
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json($devices);
    }

    public function show(Device $device)
    {
        $device->load(['deviceType', 'lab', 'sensors.sensorType', 'sensors.readings' => function($query) {
            $query->where('reading_time', '<=', now())
                ->orderBy('reading_time', 'desc')
                ->limit(100);
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
