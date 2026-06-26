<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SensorType;
use Illuminate\Http\Request;

class SensorTypeController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => SensorType::query()
                ->withCount('sensors')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $sensorType = SensorType::create($this->validated($request));

        return response()->json([
            'data' => $sensorType->loadCount('sensors'),
            'message' => 'Tipo de sensor creado correctamente.',
        ], 201);
    }

    public function show(SensorType $sensorType)
    {
        return response()->json(['data' => $sensorType->loadCount('sensors')]);
    }

    public function update(Request $request, SensorType $sensorType)
    {
        $sensorType->update($this->validated($request));

        return response()->json([
            'data' => $sensorType->refresh()->loadCount('sensors'),
            'message' => 'Tipo de sensor actualizado correctamente.',
        ]);
    }

    public function destroy(SensorType $sensorType)
    {
        if ($sensorType->sensors()->exists() || $sensorType->alertRules()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el tipo de sensor porque está siendo usado por sensores o reglas.',
            ], 409);
        }

        $sensorType->delete();

        return response()->json(['message' => 'Tipo de sensor eliminado correctamente.']);
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'min_range' => ['required', 'numeric'],
            'max_range' => ['required', 'numeric', 'gt:min_range'],
        ]);
    }
}
