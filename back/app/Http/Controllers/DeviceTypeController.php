<?php

namespace App\Http\Controllers;

use App\Models\DeviceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    public function create()
    {
        $deviceTypes = DeviceType::all();
        return view('device-types.create', compact('deviceTypes'));
    }

    public function store(Request $request)
    {
        $startTime = microtime(true);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:device_types,name',
            'description' => 'nullable|string|max:1000',
        ]);

        $deviceType = DeviceType::create($validatedData);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DeviceType store success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'device_type_id' => $deviceType->id,
            'payload_keys' => array_keys($validatedData),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return redirect()->route('device-types.create')->with('success', 'Tipo de dispositivo creado correctamente.');
    }

    public function edit(DeviceType $deviceType)
    {
        return view('device-types.edit', compact('deviceType'));
    }

    public function update(Request $request, DeviceType $deviceType)
    {
        $startTime = microtime(true);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:device_types,name,' . $deviceType->id,
            'description' => 'nullable|string|max:1000',
        ]);

        $deviceType->update($validatedData);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DeviceType update success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'device_type_id' => $deviceType->id,
            'payload_keys' => array_keys($validatedData),
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return redirect()->route('device-types.create')->with('success', 'Tipo de dispositivo actualizado correctamente.');
    }

    public function destroy(DeviceType $deviceType)
    {
        $startTime = microtime(true);

        // Verificar si el tipo de dispositivo está siendo usado
        if ($deviceType->devices()->count() > 0) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::warning('DeviceType destroy blocked: in use', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'device_type_id' => $deviceType->id,
                'duration_ms' => $durationMs,
            ]);

            return redirect()->route('device-types.create')->with('error', 'No se puede eliminar el tipo de dispositivo porque está siendo usado por dispositivos existentes.');
        }

        $deviceType->delete();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DeviceType destroy success', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'device_type_id' => $deviceType->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return redirect()->route('device-types.create')->with('success', 'Tipo de dispositivo eliminado correctamente.');
    }
}
