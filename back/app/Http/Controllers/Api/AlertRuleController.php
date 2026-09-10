<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAlertRuleRequest;
use App\Http\Requests\Api\UpdateAlertRuleRequest;
use App\Http\Resources\AlertRuleResource;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\SensorResource;
use App\Models\AlertRule;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class AlertRuleController extends Controller
{
    public function index(Request $request)
    {
        $startTime = microtime(true);

        $context = [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
        ];

        $perPage = max(1, min((int) $request->query('per_page', 50), 100));
        $deviceId = $request->query('device_id');
        if ($deviceId !== null && filter_var($deviceId, FILTER_VALIDATE_INT) === false) {
            return response()->json([
                'error' => 'Invalid parameter',
                'message' => 'device_id debe ser un entero válido.',
            ], 422);
        }

        $rules = AlertRule::with(['sensorType', 'device', 'sensor'])
            ->when($deviceId, fn ($query) => $query->where('device_id', $deviceId))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('AlertRule index request', $context + [
            'count' => $rules->total(),
            'duration_ms' => $durationMs,
        ]);

        return AlertRuleResource::collection($rules);
    }

    public function create(): JsonResponse
    {
        $startTime = microtime(true);

        $result = $this->metadataPayload();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('AlertRule create metadata request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json($result);
    }

    public function store(StoreAlertRuleRequest $request)
    {
        $startTime = microtime(true);

        $context = [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
        ];

        try {
            $alertRule = AlertRule::create($this->validatedPayload($request));
            $alertRule->load(['sensorType', 'device', 'sensor']);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('AlertRule store success', $context + [
                'alert_rule_id' => $alertRule->id,
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return (new AlertRuleResource($alertRule))
                ->response()
                ->setStatusCode(201);
        } catch (QueryException $e) {
            Log::error('AlertRule store database error', $context + [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible crear la regla de alerta.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('AlertRule store unexpected error', $context + [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error creating alert rule',
                'message' => 'Se produjo un error inesperado creando la regla de alerta.',
            ], 500);
        }
    }

    public function show(AlertRule $alertRule)
    {
        $startTime = microtime(true);

        $alertRule->load(['sensorType', 'device', 'sensor']);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('AlertRule show request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'alert_rule_id' => $alertRule->id,
            'duration_ms' => $durationMs,
        ]);

        return new AlertRuleResource($alertRule);
    }

    public function update(UpdateAlertRuleRequest $request, AlertRule $alertRule)
    {
        $startTime = microtime(true);

        $context = [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'alert_rule_id' => $alertRule->id,
        ];

        try {
            $alertRule->update($this->validatedPayload($request));
            $alertRule->load(['sensorType', 'device', 'sensor']);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('AlertRule update success', $context + [
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return new AlertRuleResource($alertRule);
        } catch (QueryException $e) {
            Log::error('AlertRule update database error', $context + [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible actualizar la regla de alerta.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('AlertRule update unexpected error', $context + [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error updating alert rule',
                'message' => 'Se produjo un error inesperado actualizando la regla de alerta.',
            ], 500);
        }
    }

    public function destroy(AlertRule $alertRule): JsonResponse
    {
        $startTime = microtime(true);

        try {
            $alertRule->delete();

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('AlertRule destroy success', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'alert_rule_id' => $alertRule->id,
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'message' => 'Regla de alerta eliminada correctamente.',
            ]);
        } catch (QueryException $e) {
            Log::error('AlertRule destroy database error', [
                'alert_rule_id' => $alertRule->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible eliminar la regla de alerta.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('AlertRule destroy unexpected error', [
                'alert_rule_id' => $alertRule->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error deleting alert rule',
                'message' => 'Se produjo un error inesperado eliminando la regla de alerta.',
            ], 500);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function validatedPayload(StoreAlertRuleRequest $request): array
    {
        $validated = $request->validated();

        if (! empty($validated['sensor_id'])) {
            $sensor = Sensor::find($validated['sensor_id']);
            if (! $sensor) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sensor_id' => ['El sensor seleccionado no existe.'],
                ]);
            }
            $validated['device_id'] = $sensor->device_id;
            $validated['sensor_type_id'] = $sensor->sensor_type_id;
        }

        return $validated;
    }

    /**
     * @return array<string,mixed>
     */
    private function metadataPayload(): array
    {
        $sensorTypes = SensorType::orderBy('name')->get();
        $devices = Device::with('lab')->orderBy('name')->get();
        $sensors = Sensor::with(['device', 'sensorType'])->orderBy('name')->get();

        return [
            'sensor_types' => $sensorTypes,
            'devices' => DeviceResource::collection($devices)->resolve(),
            'sensors' => SensorResource::collection($sensors)->resolve(),
        ];
    }
}
