<?php

namespace App\Http\Controllers;

use App\Models\SensorType;
use Illuminate\Http\Request;

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
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'min_range' => 'required|numeric',
            'max_range' => 'required|numeric|gt:min_range',
        ]);

        SensorType::create($validatedData);

        return redirect()->route('sensor-types.create')->with('success', 'Tipo de sensor creado correctamente.');
    }

    public function edit(SensorType $sensorType)
    {
        return view('sensor-types.edit', compact('sensorType'));
    }

    public function update(Request $request, SensorType $sensorType)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'min_range' => 'required|numeric',
            'max_range' => 'required|numeric|gt:min_range',
        ]);

        $sensorType->update($validatedData);

        return redirect()->route('sensor-types.create')->with('success', 'Tipo de sensor actualizado correctamente.');
    }

    public function destroy(SensorType $sensorType)
    {
        $sensorType->delete();
        return redirect()->route('sensor-types.create')->with('success', 'Tipo de sensor eliminado correctamente.');
    }
}
