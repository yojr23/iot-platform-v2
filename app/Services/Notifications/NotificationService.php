<?php

namespace App\Services\Notifications;

use App\Events\NewAlertTriggered;
use App\Models\Alert;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function broadcastNewAlert(Alert $alert): void
    {
        event(new NewAlertTriggered($alert));
    }

    public function notifyDangerAlertByEmail(Alert $alert): bool
    {
        $alert->loadMissing('alertRule', 'sensorReading.sensor.sensorType', 'sensorReading.sensor.device.lab');

        $severity = strtolower($alert->alertRule->severity ?? '');
        if ($severity !== 'danger') {
            Log::debug('NotificationService: alerta no es danger, no se envía correo', [
                'alert_id' => $alert->id,
                'severity' => $severity,
            ]);

            return false;
        }

        $sensorReading = $alert->sensorReading;
        if (! $sensorReading) {
            Log::warning('NotificationService: alerta sin lectura asociada', [
                'alert_id' => $alert->id,
            ]);

            return false;
        }

        $sensor = $sensorReading->sensor;
        $device = $sensor?->device;
        $sensorType = $sensor?->sensorType;
        $location = $device && $device->lab ? $device->lab->name : 'Ubicación desconocida';

        $rateLimitSeconds = (int) SystemSetting::get('danger_email_rate_limit_seconds', 60);
        $rateLimitSeconds = max(0, $rateLimitSeconds);

        $rateLimitKey = sprintf(
            'danger_alert_email:%d:%d:%d',
            $alert->alert_rule_id ?? 0,
            $sensor?->id ?? 0,
            $device?->id ?? 0
        );

        if ($rateLimitSeconds > 0) {
            $acquired = Cache::add($rateLimitKey, $alert->id, now()->addSeconds($rateLimitSeconds));
            if (! $acquired) {
                Log::info('NotificationService: correo suprimido por rate limit', [
                    'alert_id' => $alert->id,
                    'rate_limit_key' => $rateLimitKey,
                    'rate_limit_seconds' => $rateLimitSeconds,
                ]);

                return false;
            }
        }

        $alertDetails = [
            'alert_id' => $alert->id,
            'device' => $device?->name ?? 'Dispositivo desconocido',
            'location' => $location,
            'sensor' => $sensor?->name ?? 'Sensor desconocido',
            'sensor_type' => $sensorType?->name ?? 'Tipo desconocido',
            'unit' => $sensorType?->unit ?? '',
            'rule_name' => $alert->alertRule->name ?? 'Regla sin nombre',
            'severity' => $severity !== '' ? strtoupper($severity) : 'DANGER',
            'threshold_min' => $alert->alertRule->min_value,
            'threshold_max' => $alert->alertRule->max_value,
            'alert_message' => $alert->alertRule->message,
            'value' => $sensorReading->value,
            'detected_at' => optional($sensorReading->reading_time)->format('Y-m-d H:i:s')
                ?? optional($alert->created_at)->format('Y-m-d H:i:s'),
        ];

        $emailSent = Alert::sendDangerAlertEmail($alertDetails);

        if (! $emailSent) {
            Log::warning('NotificationService: fallo de envío de correo danger', [
                'alert_id' => $alert->id,
            ]);
        }

        return $emailSent;
    }
}

