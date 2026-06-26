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

class AlertRuleController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 50), 100));
        $deviceId = $request->query('device_id');

        $rules = AlertRule::with(['sensorType', 'device', 'sensor'])
            ->when($deviceId, fn ($query) => $query->where('device_id', $deviceId))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return AlertRuleResource::collection($rules);
    }

    public function create(): JsonResponse
    {
        return response()->json($this->metadataPayload());
    }

    public function store(StoreAlertRuleRequest $request)
    {
        $alertRule = AlertRule::create($this->validatedPayload($request));
        $alertRule->load(['sensorType', 'device', 'sensor']);

        return (new AlertRuleResource($alertRule))
            ->response()
            ->setStatusCode(201);
    }

    public function show(AlertRule $alertRule)
    {
        $alertRule->load(['sensorType', 'device', 'sensor']);

        return new AlertRuleResource($alertRule);
    }

    public function update(UpdateAlertRuleRequest $request, AlertRule $alertRule)
    {
        $alertRule->update($this->validatedPayload($request));
        $alertRule->load(['sensorType', 'device', 'sensor']);

        return new AlertRuleResource($alertRule);
    }

    public function destroy(AlertRule $alertRule): JsonResponse
    {
        $alertRule->delete();

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
