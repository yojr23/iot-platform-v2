<?php

namespace App\Services\Notifications;

use App\Exceptions\DangerAlertEmailDeliveryException;
use App\Models\Alert;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Same outcome as {@see notifyDangerAlertByEmail()}, but throws
     * {@see DangerAlertEmailDeliveryException} when an email was actually attempted and the send
     * failed — never for an intentional no-op (not danger / no reading / rate limited). Intended
     * for `SendDangerAlertEmailJob`, which lets the exception bubble so the queue retries only a
     * real delivery failure.
     */
    public function notifyDangerAlertByEmailOrFail(Alert $alert): void
    {
        $result = $this->attemptDangerAlertEmail($alert);

        if (! $result['sent'] && $result['reason'] === null) {
            throw new DangerAlertEmailDeliveryException(
                "Danger alert email delivery failed for alert {$alert->id}"
            );
        }
    }

    public function notifyDangerAlertByEmail(Alert $alert): bool
    {
        return $this->attemptDangerAlertEmail($alert)['sent'];
    }

    /**
     * @return array{sent: bool, reason: string|null} `reason` is one of 'not_danger',
     *                                                'no_reading', 'rate_limited' for an intentional no-op, or null once a real send was
     *                                                attempted (whether or not it succeeded — see `sent`).
     */
    private function attemptDangerAlertEmail(Alert $alert): array
    {
        Log::info('NotificationService:notifyDangerAlertByEmail entry', ['alert_id' => $alert->id]);

        $startTime = microtime(true);
        $alert->loadMissing('alertRule', 'sensorReading.sensor.sensorType', 'sensorReading.sensor.device.lab');

        $severity = strtolower($alert->alertRule->severity ?? '');
        if ($severity !== 'danger') {
            Log::debug('NotificationService: alerta no es danger, no se envía correo', [
                'alert_id' => $alert->id,
                'severity' => $severity,
            ]);

            return ['sent' => false, 'reason' => 'not_danger'];
        }

        $sensorReading = $alert->sensorReading;
        if (! $sensorReading) {
            Log::warning('NotificationService: alerta sin lectura asociada', [
                'alert_id' => $alert->id,
            ]);

            return ['sent' => false, 'reason' => 'no_reading'];
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

                return ['sent' => false, 'reason' => 'rate_limited'];
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

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        if (! $emailSent) {
            // PLAN.md Stage 8.2 / audit.md §13: the rate-limit key above is a "reservation", not a
            // record of an actual send. Acquiring it before the send meant a failed/unavailable
            // SMTP attempt permanently occupied the window and suppressed a legitimate retry until
            // it expired. Release it on failure so the next triggering alert (or a queued retry) is
            // still eligible to send within the same window.
            if ($rateLimitSeconds > 0) {
                Cache::forget($rateLimitKey);
            }

            Log::warning('NotificationService:fallo de envío de correo danger', [
                'alert_id' => $alert->id,
                'duration_ms' => $durationMs,
            ]);
        }

        Log::info('NotificationService:notifyDangerAlertByEmail completed', [
            'alert_id' => $alert->id,
            'email_sent' => $emailSent,
            'duration_ms' => $durationMs,
        ]);

        if ($durationMs > 100) {
            Log::warning('NotificationService:notifyDangerAlertByEmail slow execution', ['duration_ms' => $durationMs]);
        }

        return ['sent' => $emailSent, 'reason' => null];
    }
}
