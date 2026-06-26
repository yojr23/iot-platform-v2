<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceTypeController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => DeviceType::query()
                ->withCount('devices')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $deviceType = DeviceType::create($this->validated($request));

        return response()->json([
            'data' => $deviceType->loadCount('devices'),
            'message' => 'Tipo de dispositivo creado correctamente.',
        ], 201);
    }

    public function show(DeviceType $deviceType)
    {
        return response()->json(['data' => $deviceType->loadCount('devices')]);
    }

    public function update(Request $request, DeviceType $deviceType)
    {
        $deviceType->update($this->validated($request, $deviceType));

        return response()->json([
            'data' => $deviceType->refresh()->loadCount('devices'),
            'message' => 'Tipo de dispositivo actualizado correctamente.',
        ]);
    }

    public function destroy(DeviceType $deviceType)
    {
        if ($deviceType->devices()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el tipo de dispositivo porque está siendo usado por dispositivos existentes.',
            ], 409);
        }

        $deviceType->delete();

        return response()->json(['message' => 'Tipo de dispositivo eliminado correctamente.']);
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request, ?DeviceType $deviceType = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('device_types', 'name')->ignore($deviceType?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
