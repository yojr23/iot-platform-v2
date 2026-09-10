<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\DeviceStatusSnapshotResource;
use App\Http\Resources\SensorResource;
use App\Models\Device;
use App\Models\DomainEventOutbox;
use App\Services\DeviceService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeviceApiController extends Controller
{
    public function __construct(private DeviceService $deviceService)
    {
    }
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

            return response()->json($devices->through(
                fn (Device $device): array => (new DeviceResource($device))->resolve($request)
            ));
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

    /**
     * Bounded cursor snapshot for lifecycle recovery. The latest durable outbox ID is the
     * authoritative sequence watermark, so delayed broadcasts cannot regress recovered state.
     */
    public function statusSnapshot(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 100), 100));
        $cursor = max(0, (int) $request->query('cursor', 0));

        $latestStatusEvents = DomainEventOutbox::query()
            ->selectRaw('aggregate_id, MAX(id) as event_sequence')
            ->where('event_type', 'device.status.changed')
            ->where('aggregate_type', 'device')
            ->groupBy('aggregate_id');

        $devices = Device::query()
            ->leftJoinSub($latestStatusEvents, 'latest_status_events', function ($join): void {
                $join->on('devices.id', '=', 'latest_status_events.aggregate_id');
            })
            ->where('devices.id', '>', $cursor)
            ->orderBy('devices.id')
            ->limit($perPage + 1)
            ->get(['devices.*', 'latest_status_events.event_sequence']);

        $hasMore = $devices->count() > $perPage;
        $items = $devices->take($perPage)->values();

        return response()->json([
            'data' => DeviceStatusSnapshotResource::collection($items)->resolve($request),
            'next_cursor' => $hasMore ? $items->last()?->id : null,
        ]);
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

            return response()->json((new DeviceResource($device))->resolve($request));
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

    public function store(Request $request)
    {
        $validated = $this->validatedDevicePayload($request);
        $status = array_key_exists('status', $validated) ? (bool) $validated['status'] : true;

        try {
            $device = Device::create($validated + [
                'status' => $status,
                'is_active' => $status,
            ]);

            $device->statusLogs()->create([
                'status' => $status,
                'changed_at' => now(),
            ]);

            $device->load(['deviceType', 'lab', 'sensors.sensorType']);

            return (new DeviceResource($device))
                ->additional(['message' => 'Dispositivo creado correctamente.'])
                ->response()
                ->setStatusCode(201);
        } catch (QueryException $e) {
            Log::error('Database error while creating device', [
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible crear el dispositivo.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error while creating device', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error creating device',
                'message' => 'Se produjo un error inesperado creando el dispositivo.',
            ], 500);
        }
    }

    public function update(Request $request, Device $device)
    {
        $validated = $this->validatedDevicePayload($request, $device);

        // PLAN.md Stage 4.2/G0D row B5: status is a distinct transition, not a plain attribute —
        // route it through DeviceService::changeStatus() (log + device.status.changed) exactly
        // like updateStatus() does, instead of writing a status log here with no event.
        $statusChange = array_key_exists('status', $validated) ? (bool) $validated['status'] : null;
        unset($validated['status'], $validated['is_active']);

        try {
            if ($validated !== []) {
                $device->update($validated);
            }

            if ($statusChange !== null) {
                $this->deviceService->changeStatus($device, $statusChange);
            }

            $device->refresh()->load(['deviceType', 'lab', 'sensors.sensorType']);

            return (new DeviceResource($device))
                ->additional(['message' => 'Dispositivo actualizado correctamente.']);
        } catch (QueryException $e) {
            Log::error('Database error while updating device', [
                'device_id' => $device->id,
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible actualizar el dispositivo.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error while updating device', [
                'device_id' => $device->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error updating device',
                'message' => 'Se produjo un error inesperado actualizando el dispositivo.',
            ], 500);
        }
    }

    public function destroy(Device $device)
    {
        if ($device->sensors()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el dispositivo porque tiene sensores asociados.',
            ], 409);
        }

        try {
            $device->statusLogs()->delete();
            $device->delete();

            return response()->json(['message' => 'Dispositivo eliminado correctamente.']);
        } catch (QueryException $e) {
            Log::error('Database error while deleting device', [
                'device_id' => $device->id,
                'sql_state' => $e->errorInfo[0] ?? null,
                'db_error_code' => $e->errorInfo[1] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible eliminar el dispositivo.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unexpected error while deleting device', [
                'device_id' => $device->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error deleting device',
                'message' => 'Se produjo un error inesperado eliminando el dispositivo.',
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

            // PLAN.md Stage 4.2 (audit RC2/RC3): single command path — updates status+is_active,
            // writes the status log, and emits device.status.changed exactly once via the domain
            // outbox. This previously mutated the row directly with no log and no event at all.
            $this->deviceService->changeStatus($device, $newStatus);

            $device->refresh();

            Log::info('Device status updated successfully', [
                'device_id' => $device->id,
                'status' => $device->status,
                'is_active' => $device->is_active,
            ]);

            return response()->json([
                'message' => 'Estado del dispositivo actualizado',
                'device' => (new DeviceResource($device))->resolve($request),
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

    public function sensors(Request $request, Device $device)
    {
        try {
            $sensors = $device->sensors()
                ->with(['device', 'sensorType'])
                ->orderBy('name')
                ->get();

            return response()->json(
                SensorResource::collection($sensors)->resolve($request)
            );
        } catch (Throwable $e) {
            Log::error('Unexpected error while fetching device sensors', [
                'device_id' => $device->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error retrieving device sensors',
                'message' => 'No fue posible obtener sensores del dispositivo.',
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

    /**
     * @return array<string,mixed>
     */
    private function validatedDevicePayload(Request $request, ?Device $device = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'serial_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('devices', 'serial_number')->ignore($device?->id),
            ],
            'device_type_id' => ['required', 'exists:device_types,id'],
            'lab_id' => ['required', 'exists:labs,id'],
            'status' => ['sometimes', 'boolean'],
            'ip_address' => ['nullable', 'ip'],
            'mac_address' => ['nullable', 'string', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'],
        ]);
    }
}
