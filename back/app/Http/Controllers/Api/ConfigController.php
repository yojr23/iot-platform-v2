<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateAlertConfigRequest;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function publicConfig(): JsonResponse
    {
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

    public function alerts(): JsonResponse
    {
        return response()->json($this->alertSettings());
    }

    public function updateAlerts(UpdateAlertConfigRequest $request): JsonResponse
    {
        $validated = $request->validated();

        SystemSetting::set('mail_enabled', (int) $validated['mail_enabled'], 'boolean', 'mail');
        SystemSetting::set('alert_sound_enabled', (int) $validated['alert_sound_enabled'], 'boolean', 'alerts');
        SystemSetting::set('alert_threshold', $validated['alert_threshold'], 'integer', 'alerts');
        SystemSetting::set('sensor_update_interval', $validated['sensor_update_interval'], 'integer', 'alerts');
        SystemSetting::set('danger_email_rate_limit_seconds', $validated['danger_email_rate_limit_seconds'], 'integer', 'alerts');
        SystemSetting::clearCache();

        return response()->json($this->alertSettings() + [
            'message' => 'Configuracion de alertas actualizada correctamente.',
        ]);
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
