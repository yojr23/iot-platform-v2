<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\DeviceService;
use App\Events\DeviceCommunicationReceived;


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
        $devices = Device::with(['deviceType', 'classroom', 'sensors'])
        ->orderBy('created_at', 'desc')
        ->paginate(10);
        
        $deviceTypes = DeviceType::all(); // Obtener todos los tipos de dispositivos
        $classrooms = Classroom::all();   // Obtener todas las aulas (opcional)
        
        return view('devices.index', compact('devices', 'deviceTypes', 'classrooms'));
    }

    public function create()
    {
        $deviceTypes = DeviceType::all();
        $classrooms = Classroom::all();
        return view('devices.create', compact('deviceTypes', 'classrooms'));
    }

    public function store(Request $request)
    {
        Log::info('Solicitud recibida para crear un dispositivo', $request->all());

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'serial_number' => 'required|string|unique:devices',
            'device_type_id' => 'required|exists:device_types,id',
            'classroom_id' => 'required|exists:classrooms,id',
            'ip_address' => 'nullable|ip',
            'mac_address' => 'nullable|string|regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
            'status' => 'boolean',
        ]);

        try {
            $device = $this->service->createDevice($validatedData);
            Log::info('Dispositivo creado exitosamente', ['device' => $device]);

            return redirect()->route('devices.index')->with('success', 'Dispositivo creado correctamente');
        } catch (\Exception $e) {
            Log::error('Error al crear dispositivo', ['error' => $e->getMessage()]);

            return redirect()->route('devices.index')->with('error', 'Error al crear el dispositivo. Por favor intente nuevamente.');
        }
    }

    public function show(Device $device)
    {
        $device->load(['deviceType', 'classroom', 'sensors.sensorType', 'statusLogs']);
        return view('devices.show', compact('device'));
    }

    public function edit(Device $device)
    {
        $deviceTypes = DeviceType::all();
        $classrooms = Classroom::all();
        return view('devices.edit', compact('device', 'deviceTypes', 'classrooms'));
    }

    public function update(Request $request, Device $device)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'nullable|ip',
            'mac_address' => 'nullable|string|regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/',
            'classroom_id' => 'required|exists:classrooms,id',
        ]);

        try {
            $device->update($validated);

            return redirect()->route('devices.index')
                ->with('success', 'Dispositivo actualizado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al actualizar dispositivo: ' . $e->getMessage());
            return back()->withInput()
                ->with('error', 'Error al actualizar el dispositivo. Por favor intente nuevamente.');
        }
    }

    public function destroy(Device $device)
    {
        try {
            $device->delete();
            return redirect()->route('devices.index')
                ->with('success', 'Dispositivo eliminado exitosamente');
        } catch (\Exception $e) {
            Log::error('Error al eliminar dispositivo: ' . $e->getMessage());
            return back()->with('error', 'Error al eliminar el dispositivo. Por favor intente nuevamente.');
        }
    }

    public function toggleStatus(Device $device)
    {
        try {
            $newStatus = !$device->status;
            $device->update([
                'status' => $newStatus,
                'is_active' => $newStatus
            ]);
            
            // Registrar el cambio de estado
            $device->statusLogs()->create([
                'status' => $newStatus,
                'changed_at' => now(),
            ]);
            
            $statusText = $newStatus ? 'activado' : 'desactivado';
            return back()->with('success', "Dispositivo {$statusText} correctamente");
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado del dispositivo: ' . $e->getMessage());
            return back()->with('error', 'Error al cambiar el estado del dispositivo');
        }
    }

    public function registerCommunication(Request $request, Device $device)
    {
        // Lógica para registrar comunicación
        event(new DeviceCommunicationReceived($device));

        return response()->json(['message' => 'Comunicación registrada exitosamente']);
    }
}
