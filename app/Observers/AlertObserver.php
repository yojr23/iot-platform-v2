<?php

namespace App\Observers;

use App\Models\Alert;
use Illuminate\Support\Facades\Log;

class AlertObserver
{
    public function created(Alert $alert): void
    {
        $alert->loadMissing('alertRule', 'sensorReading.sensor.device.classroom');

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
        $location = $device && $device->classroom ? $device->classroom->name : 'Ubicación desconocida';

        $alertDetails = [
            'device' => $device?->name ?? 'Dispositivo desconocido',
            'location' => $location,
            'sensor' => $sensor?->name ?? 'Sensor desconocido',
            'alert_message' => $alert->alertRule->message,
            'value' => $sensorReading->value,
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
