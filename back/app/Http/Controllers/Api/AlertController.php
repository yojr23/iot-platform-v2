<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Services\Alerts\AlertLifecycleService;
use App\Services\Alerts\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class AlertController extends Controller
{
    public function __construct(
        private AlertService $alertService,
        private AlertLifecycleService $alertLifecycleService,
    ) {
    }

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

        try {
            $perPage = $this->perPage($request);
            $resolved = $request->query('resolved');

            $alerts = Alert::withContext()
                ->when($resolved !== null, fn ($query) => $query->where('resolved', filter_var($resolved, FILTER_VALIDATE_BOOLEAN)))
                ->orderByDesc('created_at')
                ->paginate($perPage);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Alert index request', $context + [
                'count' => $alerts->total(),
                'duration_ms' => $durationMs,
            ]);

            return AlertResource::collection($alerts);
        } catch (QueryException $e) {
            Log::error('Alert index database error', $context + [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar las alertas.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Alert index unexpected error', $context + [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error fetching alerts',
                'message' => 'Se produjo un error inesperado consultando alertas.',
            ], 500);
        }
    }

    public function unresolved(Request $request)
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
            $alerts = Alert::withContext()
                ->active()
                ->orderByDesc('created_at')
                ->paginate($this->perPage($request));

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Alert unresolved request', $context + [
                'count' => $alerts->total(),
                'duration_ms' => $durationMs,
            ]);

            return AlertResource::collection($alerts);
        } catch (QueryException $e) {
            Log::error('Alert unresolved database error', $context + [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar alertas no resueltas.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Alert unresolved unexpected error', $context + [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error fetching unresolved alerts',
                'message' => 'Se produjo un error inesperado consultando alertas no resueltas.',
            ], 500);
        }
    }

    public function show(Alert $alert)
    {
        $startTime = microtime(true);

        try {
            $alert->loadMissing([
                'sensorReading.sensor.sensorType',
                'sensorReading.sensor.device.lab',
                'alertRule',
            ]);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Alert show request', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'alert_id' => $alert->id,
                'duration_ms' => $durationMs,
            ]);

            return new AlertResource($alert);
        } catch (QueryException $e) {
            Log::error('Alert show database error', [
                'alert_id' => $alert->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible consultar la alerta.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Alert show unexpected error', [
                'alert_id' => $alert->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error fetching alert',
                'message' => 'Se produjo un error inesperado consultando la alerta.',
            ], 500);
        }
    }

    public function active(): JsonResponse
    {
        $startTime = microtime(true);

        $result = [
            'count' => $this->alertService->getActiveAlertsCount(),
            'alerts' => AlertResource::collection($this->alertService->getActiveAlertsList(10))->resolve(),
        ];

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Alert active request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'active_count' => $result['count'],
            'duration_ms' => $durationMs,
        ]);

        return response()->json($result);
    }

    public function resolve(Alert $alert)
    {
        $startTime = microtime(true);

        try {
            $this->alertLifecycleService->resolve($alert);

            $alert->loadMissing([
                'sensorReading.sensor.sensorType',
                'sensorReading.sensor.device.lab',
                'alertRule',
            ]);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Alert resolve request', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'alert_id' => $alert->id,
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return new AlertResource($alert);
        } catch (Throwable $e) {
            Log::error('Alert resolve error', [
                'alert_id' => $alert->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error resolving alert',
                'message' => 'No fue posible resolver la alerta.',
            ], 500);
        }
    }

    public function resolveAll(): JsonResponse
    {
        $startTime = microtime(true);

        try {
            // PLAN.md Stage 4.2 (audit RC2): bounded per-alert chunks through the transition owner,
            // never a mass query-builder update() — that bypassed AlertObserver and emitted zero
            // alert.resolved facts.
            $resolvedCount = $this->alertLifecycleService->resolveAll();

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Alert resolveAll request', [
                'ip' => request()->ip(),
                'method' => request()->method(),
                'path' => request()->path(),
                'request_id' => request()->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'resolved_count' => $resolvedCount,
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'message' => 'Todas las alertas activas fueron marcadas como resueltas.',
                'resolved_count' => $resolvedCount,
            ]);
        } catch (Throwable $e) {
            Log::error('Alert resolveAll error', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error resolving all alerts',
                'message' => 'No fue posible resolver todas las alertas.',
            ], 500);
        }
    }

    private function perPage(Request $request): int
    {
        $raw = $request->query('per_page', 20);
        $perPage = filter_var($raw, FILTER_VALIDATE_INT);
        if ($perPage === false) {
            $perPage = 20;
        }

        return max(1, min($perPage, 100));
    }
}
