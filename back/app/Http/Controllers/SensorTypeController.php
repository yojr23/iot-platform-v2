<?php

namespace App\Http\Controllers;

use App\Models\SensorType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SensorTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function create()
    {
        $sensorTypes = SensorType::all();
        return view('sensor-types.create', compact('sensorTypes'));
    }

    public function store(Request $request)
    {
        $startTime = microtime(true);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'min_range' => 'required|numeric',
            'max_range' => 'required|numeric|gt:min_range',
        ]);

        $sensorType = SensorType::create($validatedData);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('SensorType store success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'sensor_type_id' => $sensorType->id,
            'payload_keys' => array_keys($validatedData),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return redirect()->route('sensor-types.create')->with('success', 'Tipo de sensor creado correctamente.');
    }

    public function edit(SensorType $sensorType)
    {
        return view('sensor-types.edit', compact('sensorType'));
    }

    public function update(Request $request, SensorType $sensorType)
    {
        $startTime = microtime(true);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'min_range' => 'required|numeric',
            'max_range' => 'required|numeric|gt:min_range',
        ]);

        $sensorType->update($validatedData);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('SensorType update success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'sensor_type_id' => $sensorType->id,
            'payload_keys' => array_keys($validatedData),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return redirect()->route('sensor-types.create')->with('success', 'Tipo de sensor actualizado correctamente.');
    }

    public function destroy(SensorType $sensorType)
    {
        $startTime = microtime(true);

        $sensorType->delete();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('SensorType destroy success', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'sensor_type_id' => $sensorType->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return redirect()->route('sensor-types.create')->with('success', 'Tipo de sensor eliminado correctamente.');
    }
}
