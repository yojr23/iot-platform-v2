<?php

namespace App\Http\Controllers;

use App\Models\DeviceType;
use Illuminate\Http\Request;

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
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:device_types,name',
            'description' => 'nullable|string|max:1000',
        ]);

        DeviceType::create($validatedData);

        return redirect()->route('device-types.create')->with('success', 'Tipo de dispositivo creado correctamente.');
    }

    public function edit(DeviceType $deviceType)
    {
        return view('device-types.edit', compact('deviceType'));
    }

    public function update(Request $request, DeviceType $deviceType)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:device_types,name,' . $deviceType->id,
            'description' => 'nullable|string|max:1000',
        ]);

        $deviceType->update($validatedData);

        return redirect()->route('device-types.create')->with('success', 'Tipo de dispositivo actualizado correctamente.');
    }

    public function destroy(DeviceType $deviceType)
    {
        // Verificar si el tipo de dispositivo está siendo usado
        if ($deviceType->devices()->count() > 0) {
            return redirect()->route('device-types.create')->with('error', 'No se puede eliminar el tipo de dispositivo porque está siendo usado por dispositivos existentes.');
        }

        $deviceType->delete();
        return redirect()->route('device-types.create')->with('success', 'Tipo de dispositivo eliminado correctamente.');
    }
}
