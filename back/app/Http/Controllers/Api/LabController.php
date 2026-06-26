<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use Illuminate\Http\Request;

class LabController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Lab::query()
                ->withCount('devices')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $lab = Lab::create($this->validated($request));

        return response()->json([
            'data' => $lab->loadCount('devices'),
            'message' => 'Laboratorio creado correctamente.',
        ], 201);
    }

    public function show(Lab $lab)
    {
        return response()->json(['data' => $lab->loadCount('devices')]);
    }

    public function update(Request $request, Lab $lab)
    {
        $lab->update($this->validated($request));

        return response()->json([
            'data' => $lab->refresh()->loadCount('devices'),
            'message' => 'Laboratorio actualizado correctamente.',
        ]);
    }

    public function destroy(Lab $lab)
    {
        if ($lab->devices()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el laboratorio porque tiene dispositivos asociados.',
            ], 409);
        }

        $lab->delete();

        return response()->json(['message' => 'Laboratorio eliminado correctamente.']);
    }

    /**
     * @return array<string,mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'area' => ['required', 'string', 'max:255'],
            'process_line' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
