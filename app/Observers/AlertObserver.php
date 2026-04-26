<?php

namespace App\Observers;

use App\Models\Alert;
use App\Models\SystemSetting;
use App\Events\NewAlertTriggered;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AlertObserver
{
    public function created(Alert $alert): void
    {
        $alert->loadMissing('alertRule', 'sensorReading.sensor.sensorType', 'sensorReading.sensor.device.lab');

        event(new NewAlertTriggered($alert));

        $severity = strtolower($alert->alertRule->severity ?? '');

        if ($severity !== 'danger') {
            Log::debug('AlertObserver: alerta creada pero no es de peligro, se omite correo', [
                'alert_id' => $alert->id,
                'severity' => $severity,
            ]);
            return;
        }

        $sensorReading = $alert->sensorReading;

        if (! $sensorReading) {
            Log::warning('AlertObserver: alerta sin lectura asociada, no se puede enviar correo', [
                'alert_id' => $alert->id,
            ]);
            return;
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
                Log::info('AlertObserver: correo de alerta suprimido por rate limit', [
                    'alert_id' => $alert->id,
                    'rate_limit_key' => $rateLimitKey,
                    'rate_limit_seconds' => $rateLimitSeconds,
                ]);
                return;
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
            'detected_at' => optional($sensorReading->reading_time)->format('Y-m-d H:i:s') ?? optional($alert->created_at)->format('Y-m-d H:i:s'),
        ];

        Log::info('AlertObserver: enviando correo por alerta de peligro', [
            'alert_id' => $alert->id,
            'sensor_reading_id' => $sensorReading->id,
        ]);

        $emailSent = Alert::sendDangerAlertEmail($alertDetails);

        if (! $emailSent) {
            Log::warning('AlertObserver: no se pudo enviar el correo de alerta de peligro', [
                'alert_id' => $alert->id,
            ]);
        }
    }
}
