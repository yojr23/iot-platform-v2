<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Lab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\DeviceService;
use App\Events\DeviceCommunicationReceived;
use Throwable;


class DeviceController extends Controller
{
    protected $service;

    public function __construct(DeviceService $service)
    {
        $this->service = $service;

        $this->middleware('admin')->except(['index', 'show']);
    }
    public function index()
    {
        $startTime = microtime(true);

        $devices = Device::with(['deviceType', 'lab', 'sensors'])
        ->orderBy('created_at', 'desc')
        ->paginate(10);
        
        $deviceTypes = DeviceType::all(); // Obtener todos los tipos de dispositivos
        $labs = Lab::all();   // Obtener todos los laboratorios (opcional)

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Device index request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'count' => $devices->total(),
            'duration_ms' => $durationMs,
        ]);
        
        return view('devices.index', compact('devices', 'deviceTypes', 'labs'));
    }

    public function create()
    {
        $deviceTypes = DeviceType::all();
        $labs = Lab::all();
        return view('devices.create', compact('deviceTypes', 'labs'));
    }

    public function store(Request $request)
    {
        $startTime = microtime(true);

        Log::info('Device store request received', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'payload_keys' => array_keys($request->all()),
        ]);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'serial_number' => 'required|string|unique:devices',
            'device_type_id' => 'required|exists:device_types,id',
            'lab_id' => 'required|exists:labs,id',
            'ip_address' => 'nullable|ip',
            'mac_address' => 'nullable|string|regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
            'status' => 'boolean',
        ]);

        try {
            $device = $this->service->createDevice($validatedData);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Device store success', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'device_id' => $device->id,
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return redirect()->route('devices.index')->with('success', 'Dispositivo creado correctamente');
        } catch (Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Device store error', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'exception' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return redirect()->route('devices.index')->with('error', 'Error al crear el dispositivo. Por favor intente nuevamente.');
        }
    }

    public function show(Device $device)
    {
        $startTime = microtime(true);

        $device->load(['deviceType', 'lab', 'sensors.sensorType', 'statusLogs']);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Device show request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'device_id' => $device->id,
            'duration_ms' => $durationMs,
        ]);

        return view('devices.show', compact('device'));
    }

    public function edit(Device $device)
    {
        $deviceTypes = DeviceType::all();
        $labs = Lab::all();
        return view('devices.edit', compact('device', 'deviceTypes', 'labs'));
    }

    public function update(Request $request, Device $device)
    {
        $startTime = microtime(true);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'nullable|ip',
            'mac_address' => 'nullable|string|regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
            'lab_id' => 'required|exists:labs,id',
        ]);

        try {
            $device->update($validated);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Device update success', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'device_id' => $device->id,
                'payload_keys' => array_keys($validated),
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return redirect()->route('devices.index')
                ->with('success', 'Dispositivo actualizado exitosamente');
        } catch (Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Device update error', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'device_id' => $device->id,
                'exception' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return back()->withInput()
                ->with('error', 'Error al actualizar el dispositivo. Por favor intente nuevamente.');
        }
    }

    public function destroy(Device $device)
    {
        $startTime = microtime(true);

        try {
            $device->delete();

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Device destroy success', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'device_id' => $device->id,
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return redirect()->route('devices.index')
                ->with('success', 'Dispositivo eliminado exitosamente');
        } catch (Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Device destroy error', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'device_id' => $device->id,
                'exception' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return back()->with('error', 'Error al eliminar el dispositivo. Por favor intente nuevamente.');
        }
    }

    public function toggleStatus(Device $device)
    {
        $startTime = microtime(true);

        try {
            $newStatus = !$device->status;

            // PLAN.md Stage 4.2 (G0D row B5): same transition owner as the API's updateStatus() —
            // update + status log + device.status.changed all happen once, from one place.
            $this->service->changeStatus($device, $newStatus);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            $statusText = $newStatus ? 'activado' : 'desactivado';

            Log::info('Device toggleStatus success', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'device_id' => $device->id,
                'new_status' => $newStatus,
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return back()->with('success', "Dispositivo {$statusText} correctamente");
        } catch (Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Device toggleStatus error', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'device_id' => $device->id,
                'exception' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return back()->with('error', 'Error al cambiar el estado del dispositivo');
        }
    }

    public function registerCommunication(Request $request, Device $device)
    {
        $startTime = microtime(true);

        // Lógica para registrar comunicación
        event(new DeviceCommunicationReceived($device));

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Device registerCommunication request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'device_id' => $device->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json(['message' => 'Comunicación registrada exitosamente']);
    }
}
