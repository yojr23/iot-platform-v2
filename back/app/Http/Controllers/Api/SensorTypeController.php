<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SensorType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

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

        try {
            $sensorType = SensorType::create($this->validated($request));
        } catch (QueryException $e) {
            Log::error('SensorType store database error', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible crear el tipo de sensor.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('SensorType store error', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error',
                'message' => 'Se produjo un error inesperado creando el tipo de sensor.',
            ], 500);
        }

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

        try {
            $sensorType->update($this->validated($request));
        } catch (QueryException $e) {
            Log::error('SensorType update database error', [
                'sensor_type_id' => $sensorType->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible actualizar el tipo de sensor.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('SensorType update error', [
                'sensor_type_id' => $sensorType->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error',
                'message' => 'Se produjo un error inesperado actualizando el tipo de sensor.',
            ], 500);
        }

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

        try {
            $sensorType->delete();
        } catch (QueryException $e) {
            Log::error('SensorType destroy database error', [
                'sensor_type_id' => $sensorType->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible eliminar el tipo de sensor.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('SensorType destroy error', [
                'sensor_type_id' => $sensorType->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error',
                'message' => 'Se produjo un error inesperado eliminando el tipo de sensor.',
            ], 500);
        }

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
