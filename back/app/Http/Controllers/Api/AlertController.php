<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Services\Alerts\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(private AlertService $alertService)
    {
    }

    public function index(Request $request)
    {
        $perPage = $this->perPage($request);
        $resolved = $request->query('resolved');

        $alerts = Alert::withContext()
            ->when($resolved !== null, fn ($query) => $query->where('resolved', filter_var($resolved, FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return AlertResource::collection($alerts);
    }

    public function unresolved(Request $request)
    {
        $alerts = Alert::withContext()
            ->active()
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request));

        return AlertResource::collection($alerts);
    }

    public function show(Alert $alert)
    {
        $alert->loadMissing([
            'sensorReading.sensor.sensorType',
            'sensorReading.sensor.device.lab',
            'alertRule',
        ]);

        return new AlertResource($alert);
    }

    public function active(): JsonResponse
    {
        return response()->json([
            'count' => $this->alertService->getActiveAlertsCount(),
            'alerts' => AlertResource::collection($this->alertService->getActiveAlertsList(10))->resolve(),
        ]);
    }

    public function resolve(Alert $alert)
    {
        $alert->update([
            'resolved' => true,
            'resolved_at' => now(),
        ]);

        $alert->loadMissing([
            'sensorReading.sensor.sensorType',
            'sensorReading.sensor.device.lab',
            'alertRule',
        ]);

        return new AlertResource($alert);
    }

    public function resolveAll(): JsonResponse
    {
        $resolvedCount = Alert::active()->update([
            'resolved' => true,
            'resolved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Todas las alertas activas fueron marcadas como resueltas.',
            'resolved_count' => $resolvedCount,
        ]);
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 20);

        return max(1, min($perPage, 100));
    }
}
