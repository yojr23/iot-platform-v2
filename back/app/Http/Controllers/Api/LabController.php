<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lab;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LabController extends Controller
{
    public function index()
    {
        $startTime = microtime(true);

        $result = Lab::query()
            ->withCount('devices')
            ->orderBy('name')
            ->get();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Lab index request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'count' => $result->count(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'data' => $result,
        ]);
    }

    public function store(Request $request)
    {
        $startTime = microtime(true);

        $lab = Lab::create($this->validated($request));

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Lab store success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'lab_id' => $lab->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'data' => $lab->loadCount('devices'),
            'message' => 'Laboratorio creado correctamente.',
        ], 201);
    }

    public function show(Lab $lab)
    {
        $startTime = microtime(true);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Lab show request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'lab_id' => $lab->id,
            'duration_ms' => $durationMs,
        ]);

        return response()->json(['data' => $lab->loadCount('devices')]);
    }

    public function update(Request $request, Lab $lab)
    {
        $startTime = microtime(true);

        $lab->update($this->validated($request));

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Lab update success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'lab_id' => $lab->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'data' => $lab->refresh()->loadCount('devices'),
            'message' => 'Laboratorio actualizado correctamente.',
        ]);
    }

    public function destroy(Lab $lab)
    {
        $startTime = microtime(true);

        if ($lab->devices()->exists()) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::warning('Lab destroy blocked: has devices', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'lab_id' => $lab->id,
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'message' => 'No se puede eliminar el laboratorio porque tiene dispositivos asociados.',
            ], 409);
        }

        $lab->delete();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Lab destroy success', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'lab_id' => $lab->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

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
