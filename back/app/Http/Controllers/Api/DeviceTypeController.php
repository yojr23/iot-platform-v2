<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;
use Illuminate\Validation\Rule;

class DeviceTypeController extends Controller
{
    public function index()
    {
        $startTime = microtime(true);

        $result = DeviceType::query()
            ->withCount('devices')
            ->orderBy('name')
            ->get();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DeviceType index request', [
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

        try {
            $deviceType = DeviceType::create($this->validated($request));
        } catch (QueryException $e) {
            Log::error('DeviceType store database error', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible crear el tipo de dispositivo.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('DeviceType store error', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error',
                'message' => 'Se produjo un error inesperado creando el tipo de dispositivo.',
            ], 500);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DeviceType store success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'device_type_id' => $deviceType->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'data' => $deviceType->loadCount('devices'),
            'message' => 'Tipo de dispositivo creado correctamente.',
        ], 201);
    }

    public function show(DeviceType $deviceType)
    {
        $startTime = microtime(true);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DeviceType show request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'device_type_id' => $deviceType->id,
            'duration_ms' => $durationMs,
        ]);

        return response()->json(['data' => $deviceType->loadCount('devices')]);
    }

    public function update(Request $request, DeviceType $deviceType)
    {
        $startTime = microtime(true);

        try {
            $deviceType->update($this->validated($request, $deviceType));
        } catch (QueryException $e) {
            Log::error('DeviceType update database error', [
                'device_type_id' => $deviceType->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible actualizar el tipo de dispositivo.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('DeviceType update error', [
                'device_type_id' => $deviceType->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error',
                'message' => 'Se produjo un error inesperado actualizando el tipo de dispositivo.',
            ], 500);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DeviceType update success', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'device_type_id' => $deviceType->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'data' => $deviceType->refresh()->loadCount('devices'),
            'message' => 'Tipo de dispositivo actualizado correctamente.',
        ]);
    }

    public function destroy(DeviceType $deviceType)
    {
        $startTime = microtime(true);

        if ($deviceType->devices()->exists()) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::warning('DeviceType destroy blocked: in use', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'device_type_id' => $deviceType->id,
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'message' => 'No se puede eliminar el tipo de dispositivo porque está siendo usado por dispositivos existentes.',
            ], 409);
        }

        try {
            $deviceType->delete();
        } catch (QueryException $e) {
            Log::error('DeviceType destroy database error', [
                'device_type_id' => $deviceType->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible eliminar el tipo de dispositivo.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('DeviceType destroy error', [
                'device_type_id' => $deviceType->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error',
                'message' => 'Se produjo un error inesperado eliminando el tipo de dispositivo.',
            ], 500);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('DeviceType destroy success', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'device_type_id' => $deviceType->id,
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

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
