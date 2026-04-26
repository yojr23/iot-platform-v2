<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\DangerAlertMail;
use App\Models\SystemSetting;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = ['sensor_reading_id', 'alert_rule_id', 'resolved', 'resolved_at'];

    public function sensorReading()
    {
        return $this->belongsTo(SensorReading::class);
    }

    public function alertRule()
    {
        return $this->belongsTo(AlertRule::class);
    }

    public static function sendDangerAlertEmail($alertDetails)
    {
        try {
            $emailData = [
                'device' => (string) $alertDetails['device'],
                'location' => (string) $alertDetails['location'],
                'sensor' => (string) $alertDetails['sensor'],
                'alert_message' => (string) $alertDetails['alert_message'],
                'value' => (string) $alertDetails['value'],
            ];

            $recipient = SystemSetting::get('mail_to')
                ?? config('mail.recipient_email')
                ?? env('MAIL_TO_ALERT')
                ?? env('MAIL_TO')
                ?? env('RECIPIENT_EMAIL')
                ?? env('recipient_email');

            if (! $recipient) {
                Log::warning('No hay destinatario configurado para alertas peligrosas. Se omitirá el envío de correo.');
                return false;
            }

            $defaultMailer = (string) SystemSetting::get(
                'mail_mailer',
                config('mail.default') ?? config('mail.mailer')
            );

            $defaultMailer = $defaultMailer !== '' ? $defaultMailer : 'log';

            $from = [
                'address' => SystemSetting::get('mail_from_address', config('mail.from.address')),
                'name' => SystemSetting::get('mail_from_name', config('mail.from.name')),
            ];

            $existingMailers = config('mail.mailers', []);
            $smtpBase = $existingMailers['smtp'] ?? ['transport' => 'smtp'];

            $smtpConfig = array_merge($smtpBase, [
                'host' => SystemSetting::get('mail_host', $smtpBase['host'] ?? config('mail.host')),
                'port' => SystemSetting::get('mail_port', $smtpBase['port'] ?? config('mail.port')),
                'username' => SystemSetting::get('mail_username', $smtpBase['username'] ?? config('mail.username')),
                'password' => SystemSetting::get('mail_password', $smtpBase['password'] ?? ''),
                'encryption' => SystemSetting::get('mail_encryption', $smtpBase['encryption'] ?? config('mail.encryption')),
            ]);

            // Aplicar la configuración de correo compatible con Laravel 9+
            config([
                'mail.default' => $defaultMailer,
                'mail.from' => $from,
                'mail.mailers.smtp' => $smtpConfig,
            ]);

            Log::debug('Enviando alerta por correo a: ' . $recipient);
            $mailable = new DangerAlertMail($emailData);
            // Asegurarnos de que el destinatario quede en el propio mailable (más compatible con Mail::fake)
            $mailable->to($recipient);
            Mail::send($mailable);

            Log::info('Correo de alerta de peligro enviado exitosamente a: ' . $recipient);
            return true;
        } catch (\Exception $e) {
            Log::error('Error enviando email de alerta: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return false;
        }
    }
}
