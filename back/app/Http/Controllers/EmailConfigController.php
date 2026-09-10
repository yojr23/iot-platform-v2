<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailConfigController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }

    /**
     * Mostrar la página de gestión de configuración de email
     */
    public function index()
    {
        $startTime = microtime(true);

        $mailSettings = [
            'mail_mailer' => SystemSetting::get('mail_mailer', config('mail.mailer')),
            'mail_host' => SystemSetting::get('mail_host', config('mail.host')),
            'mail_port' => SystemSetting::get('mail_port', config('mail.port')),
            'mail_username' => SystemSetting::get('mail_username', config('mail.username')),
            'mail_password' => SystemSetting::get('mail_password', ''),
            'mail_encryption' => SystemSetting::get('mail_encryption', config('mail.encryption')),
            'mail_from_address' => SystemSetting::get('mail_from_address', config('mail.from.address')),
            'mail_from_name' => SystemSetting::get('mail_from_name', config('mail.from.name')),
            'mail_to' => SystemSetting::get('mail_to', env('MAIL_TO_ALERT')),
        ];

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('EmailConfig index request', [
            'ip' => request()->ip(),
            'method' => request()->method(),
            'path' => request()->path(),
            'request_id' => request()->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return view('config.email_config', ['mailSettings' => $mailSettings]);
    }

    public function update(Request $request)
    {
        $startTime = microtime(true);

        $validated = $request->validate([
            'mail_mailer' => 'required|string|in:smtp,mailgun,postmark,ses,sendmail,log',
            'mail_host' => 'required|string',
            'mail_port' => 'required|numeric|min:1|max:65535',
            'mail_username' => 'required|email',
            'mail_password' => 'required|string|min:8',
            'mail_encryption' => 'required|string|in:tls,ssl',
            'mail_from_address' => 'required|email',
            'mail_from_name' => 'required|string|max:255',
            'mail_to' => 'required|email',
        ]);

        $settings = [
            'mail_mailer' => ['value' => $validated['mail_mailer'], 'type' => 'string', 'group' => 'mail'],
            'mail_host' => ['value' => $validated['mail_host'], 'type' => 'string', 'group' => 'mail'],
            'mail_port' => ['value' => $validated['mail_port'], 'type' => 'integer', 'group' => 'mail'],
            'mail_username' => ['value' => $validated['mail_username'], 'type' => 'string', 'group' => 'mail'],
            'mail_password' => ['value' => $validated['mail_password'], 'type' => 'string', 'group' => 'mail'],
            'mail_encryption' => ['value' => $validated['mail_encryption'], 'type' => 'string', 'group' => 'mail'],
            'mail_from_address' => ['value' => $validated['mail_from_address'], 'type' => 'string', 'group' => 'mail'],
            'mail_from_name' => ['value' => $validated['mail_from_name'], 'type' => 'string', 'group' => 'mail'],
            'mail_to' => ['value' => $validated['mail_to'], 'type' => 'string', 'group' => 'mail'],
        ];

        foreach ($settings as $key => $definition) {
            SystemSetting::set(
                $key,
                $definition['value'],
                $definition['type'],
                $definition['group']
            );
        }

        SystemSetting::clearCache();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('EmailConfig update request', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'request_id' => $request->header('X-Request-Id', uniqid()),
            'user_id' => auth()->id(),
            'duration_ms' => $durationMs,
        ]);

        return back()->with('success', 'Configuración de email actualizada correctamente');
    }

    public function testEmail(Request $request)
    {
        $startTime = microtime(true);

        $validated = $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
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

            config(['mail' => $mailSettings]);

            Mail::raw('Este es un email de prueba de la configuración de SINOA. Si recibiste este correo, significa que tu configuración de email es correcta.', function ($message) use ($validated) {
                $message->to($validated['test_email'])
                    ->subject('Email de Prueba - SINOA');
            });

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('EmailConfig testEmail request', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'test_email' => $validated['test_email'],
                'duration_ms' => $durationMs,
            ]);

            return back()->with('success', 'Email de prueba enviado correctamente a ' . $validated['test_email']);
        } catch (\Exception $e) {
            Log::error('EmailConfig testEmail failed', [
                'ip' => $request->ip(),
                'method' => $request->method(),
                'path' => $request->path(),
                'request_id' => $request->header('X-Request-Id', uniqid()),
                'user_id' => auth()->id(),
                'test_email' => $validated['test_email'],
                'exception' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return back()->with('error', 'Error al enviar el email de prueba: ' . $e->getMessage());
        }
    }
}
