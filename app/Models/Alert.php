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

            // Configurar toda la conexión de correo desde SystemSettings (igual que en testEmail)
            $mailSettings = [
                'driver' => SystemSetting::get('mail_mailer', config('mail.mailer')),
                'host' => SystemSetting::get('mail_host', config('mail.host')),
                'port' => SystemSetting::get('mail_port', config('mail.port')),
                'username' => SystemSetting::get('mail_username', config('mail.username')),
                'password' => SystemSetting::get('mail_password', ''),
                'encryption' => SystemSetting::get('mail_encryption', config('mail.encryption')),
                'from' => [
                    'address' => SystemSetting::get('mail_from_address', config('mail.from.address')),
                    'name' => SystemSetting::get('mail_from_name', config('mail.from.name')),
                ],
            ];

            // Aplicar la configuración completa de correo
            config(['mail' => $mailSettings]);

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
