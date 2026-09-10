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

        $alertRule->update($this->validatedPayload($request));
        $alertRule->load(['sensorType', 'device', 'sensor']);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('AlertRule update success', $context + [
            'success' => true,
            'duration_ms' => $durationMs,
        ]);

        return new AlertRuleResource($alertRule);
    }

    public function destroy(AlertRule $alertRule): JsonResponse
    {
        $startTime = microtime(true);

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
    }

    /**
     * @return array<string,mixed>
     */
    private function validatedPayload(StoreAlertRuleRequest $request): array
    {
        $validated = $request->validated();

        if (! empty($validated['sensor_id'])) {
            $sensor = Sensor::findOrFail($validated['sensor_id']);
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
