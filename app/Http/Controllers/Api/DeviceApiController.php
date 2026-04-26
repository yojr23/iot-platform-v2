<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeviceApiController extends Controller
{
    public function index(Request $request)
    {
        $requestedPerPage = $request->query('per_page', 50);
        $perPage = (int) $requestedPerPage;
        $perPage = max(1, min($perPage, 100));

        if ((string) $requestedPerPage !== (string) $perPage) {
            Log::warning('Device list requested with unexpected per_page value; clamped to safe range', [
                'requested_per_page' => $requestedPerPage,
                'effective_per_page' => $perPage,
                'ip' => $request->ip(),
            ]);
        }

        try {
            $devices = Device::with(['deviceType', 'lab', 'sensors.sensorType'])
                ->orderBy('id')
                ->paginate($perPage);

            Log::info('Device list fetched', [
                'count' => $devices->count(),
                'total' => $devices->total(),
                'per_page' => $devices->perPage(),
                'page' => $devices->currentPage(),
                'ip' => $request->ip(),
            ]);

            return response()->json($devices);
        } catch (QueryException $e) {
            Log::error('Database error while listing devices', [
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar dispositivos.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error while listing devices', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error retrieving devices',
                'message' => 'Se produjo un error inesperado consultando dispositivos.',
            ], 500);
        }
    }

    public function show(Request $request, Device $device)
    {
        try {
            $device->load(['deviceType', 'lab', 'sensors.sensorType', 'sensors.readings' => function ($query) {
                $query->where('reading_time', '<=', now())
                    ->orderBy('reading_time', 'desc')
                    ->limit(100);
            }]);

            Log::info('Device detail fetched', [
                'device_id' => $device->id,
                'sensor_count' => $device->sensors->count(),
                'ip' => $request->ip(),
            ]);

            return response()->json($device);
        } catch (QueryException $e) {
            Log::error('Database error while fetching device detail', [
                'device_id' => $device->id,
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar el dispositivo.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error while fetching device detail', [
                'device_id' => $device->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error retrieving device',
                'message' => 'Se produjo un error inesperado consultando el dispositivo.',
            ], 500);
        }
    }

    public function updateStatus(Request $request, Device $device)
    {
        $payload = $request->all();
        $unexpectedFields = $this->detectUnexpectedFields($payload, ['status']);

        Log::info('Device status update requested', [
            'device_id' => $device->id,
            'ip' => $request->ip(),
            'payload_keys' => array_keys($payload),
        ]);

        if ($unexpectedFields !== []) {
            Log::warning('Device status payload contains unexpected fields', [
                'device_id' => $device->id,
                'unexpected_fields' => $unexpectedFields,
                'payload' => $payload,
            ]);
        }

        $validator = Validator::make($payload, [
            'status' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            Log::warning('Device status update validation failed', [
                'device_id' => $device->id,
                'errors' => $validator->errors()->toArray(),
                'payload' => $payload,
            ]);

            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        try {
            $newStatus = (bool) $validated['status'];
            $device->update([
                // Mantener consistencia entre ambos flags de estado.
                'status' => $newStatus,
                'is_active' => $newStatus,
            ]);

            $device->refresh();

            Log::info('Device status updated successfully', [
                'device_id' => $device->id,
                'status' => $device->status,
                'is_active' => $device->is_active,
            ]);

            return response()->json([
                'message' => 'Estado del dispositivo actualizado',
                'device' => $device,
            ]);
        } catch (QueryException $e) {
            Log::error('Database error while updating device status', [
                'device_id' => $device->id,
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible actualizar estado del dispositivo.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error while updating device status', [
                'device_id' => $device->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error updating device status',
                'message' => 'Se produjo un error inesperado actualizando el dispositivo.',
            ], 500);
        }
    }

    /**
     * @param array<string,mixed> $payload
     * @param array<int,string> $allowedFields
     * @return array<int,string>
     */
    private function detectUnexpectedFields(array $payload, array $allowedFields): array
    {
        return array_values(array_diff(array_keys($payload), $allowedFields));
    }
}
