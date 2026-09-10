<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SensorType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SensorTypeController extends Controller
{
    public function index()
    {
        $startTime = microtime(true);

        $data = SensorType::query()
            ->withCount('sensors')
            ->orderBy('name')
            ->get();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('SensorType index request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'count' => $data->count(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json(['data' => $data]);
    }

    public function store(Request $request)
    {
        $startTime = microtime(true);

        $sensorType = SensorType::create($this->validated($request));

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('SensorType store request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'sensor_type_id' => $sensorType->id,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'data' => $sensorType->loadCount('sensors'),
            'message' => 'Tipo de sensor creado correctamente.',
        ], 201);
    }

    public function show(SensorType $sensorType)
    {
        $startTime = microtime(true);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('SensorType show request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'sensor_type_id' => $sensorType->id,
            'duration_ms' => $durationMs,
        ]);

        return response()->json(['data' => $sensorType->loadCount('sensors')]);
    }

    public function update(Request $request, SensorType $sensorType)
    {
        $startTime = microtime(true);

        $sensorType->update($this->validated($request));

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('SensorType update request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'sensor_type_id' => $sensorType->id,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'data' => $sensorType->refresh()->loadCount('sensors'),
            'message' => 'Tipo de sensor actualizado correctamente.',
        ]);
    }

    public function destroy(SensorType $sensorType)
    {
        $startTime = microtime(true);

        if ($sensorType->sensors()->exists() || $sensorType->alertRules()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el tipo de sensor porque está siendo usado por sensores o reglas.',
            ], 409);
        }

        $sensorType->delete();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('SensorType destroy request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'sensor_type_id' => $sensorType->id,
            'duration_ms' => $durationMs,
        ]);

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
