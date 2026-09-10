<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateAlertConfigRequest;
use App\Http\Requests\Api\UpdateGeneralConfigRequest;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class ConfigController extends Controller
{
    public function publicConfig(): JsonResponse
    {
        $startTime = microtime(true);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Config publicConfig request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'app_name' => SystemSetting::get('app_name', config('app.name')),
            'alert_sound_enabled' => SystemSetting::get('alert_sound_enabled', true),
            'alert_threshold' => SystemSetting::get('alert_threshold', 5),
            'sensor_update_interval' => SystemSetting::get('sensor_update_interval', 2000),
            'pusher' => [
                'key' => config('broadcasting.connections.pusher.key') ?? env('PUSHER_APP_KEY'),
                'cluster' => config('broadcasting.connections.pusher.options.cluster') ?? env('PUSHER_APP_CLUSTER'),
            ],
        ]);
    }

    public function runtime(): JsonResponse
    {
        $startTime = microtime(true);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Config runtime request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'alert_sound_enabled' => SystemSetting::get('alert_sound_enabled', true),
            'app_url' => SystemSetting::get('app_url', config('app.url')),
        ]);
    }

    /**
     * C4 (front_rebuild_plan/MAIN_PARITY_GAPS_PLAN.md): read-only environment/runtime info for the
     * admin config screen. Matches the `{ data: {...} }` envelope already used by updateGeneral()
     * above; no writes, no new service — plain framework introspection (PHP_VERSION, app()->version(),
     * app()->environment(), config('database.default')).
     */
    public function systemInfo(): JsonResponse
    {
        $startTime = microtime(true);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Config systemInfo request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json([
            'data' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
                'db_driver' => config('database.default'),
            ],
        ]);
    }

    public function alerts(): JsonResponse
    {
        $startTime = microtime(true);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Config alerts request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return response()->json($this->alertSettings());
    }

    public function updateAlerts(UpdateAlertConfigRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        $validated = $request->validated();

        try {
            SystemSetting::set('mail_enabled', (int) $validated['mail_enabled'], 'boolean', 'mail');
            SystemSetting::set('alert_sound_enabled', (int) $validated['alert_sound_enabled'], 'boolean', 'alerts');
            SystemSetting::set('alert_threshold', $validated['alert_threshold'], 'integer', 'alerts');
            SystemSetting::set('sensor_update_interval', $validated['sensor_update_interval'], 'integer', 'alerts');
            SystemSetting::set('danger_email_rate_limit_seconds', $validated['danger_email_rate_limit_seconds'], 'integer', 'alerts');
            SystemSetting::clearCache();

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Config updateAlerts success', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'payload_keys' => array_keys($validated),
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return response()->json($this->alertSettings() + [
                'message' => 'Configuracion de alertas actualizada correctamente.',
            ]);
        } catch (QueryException $e) {
            Log::error('Config updateAlerts database error', [
                'ip' => $request->ip(),
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible actualizar la configuracion de alertas.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Config updateAlerts unexpected error', [
                'ip' => $request->ip(),
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error updating alert config',
                'message' => 'Se produjo un error inesperado actualizando la configuracion.',
            ], 500);
        }
    }

    public function updateGeneral(UpdateGeneralConfigRequest $request): JsonResponse
    {
        $startTime = microtime(true);

        $validated = $request->validated();

        try {
            SystemSetting::set('app_name', $validated['app_name'], 'string', 'general');
            SystemSetting::set('app_url', $validated['app_url'], 'string', 'general');
            SystemSetting::clearCache();

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Config updateGeneral success', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'payload_keys' => array_keys($validated),
                'success' => true,
                'duration_ms' => $durationMs,
            ]);

            return response()->json([
                'data' => [
                    'app_name' => $validated['app_name'],
                    'app_url' => $validated['app_url'],
                ],
                'message' => 'Configuración general actualizada.',
            ]);
        } catch (QueryException $e) {
            Log::error('Config updateGeneral database error', [
                'ip' => $request->ip(),
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Database error',
                'message' => 'No fue posible actualizar la configuracion general.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Config updateGeneral unexpected error', [
                'ip' => $request->ip(),
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error updating general config',
                'message' => 'Se produjo un error inesperado actualizando la configuracion.',
            ], 500);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function alertSettings(): array
    {
        return [
            'mail_enabled' => SystemSetting::get('mail_enabled', true),
            'alert_sound_enabled' => SystemSetting::get('alert_sound_enabled', true),
            'alert_threshold' => SystemSetting::get('alert_threshold', 5),
            'sensor_update_interval' => SystemSetting::get('sensor_update_interval', 2000),
            'danger_email_rate_limit_seconds' => SystemSetting::get('danger_email_rate_limit_seconds', 60),
        ];
    }
}
