<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateEmailConfigRequest;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Throwable;

class EmailConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json($this->safeMailSettings());
    }

    public function update(UpdateEmailConfigRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $settings = [
            'mail_mailer' => ['value' => $validated['mail_mailer'], 'type' => 'string'],
            'mail_host' => ['value' => $validated['mail_host'], 'type' => 'string'],
            'mail_port' => ['value' => $validated['mail_port'], 'type' => 'integer'],
            'mail_username' => ['value' => $validated['mail_username'], 'type' => 'string'],
            'mail_encryption' => ['value' => $validated['mail_encryption'], 'type' => 'string'],
            'mail_from_address' => ['value' => $validated['mail_from_address'], 'type' => 'string'],
            'mail_from_name' => ['value' => $validated['mail_from_name'], 'type' => 'string'],
            'mail_to' => ['value' => $validated['mail_to'], 'type' => 'string'],
        ];

        if (! empty($validated['mail_password'])) {
            $settings['mail_password'] = ['value' => $validated['mail_password'], 'type' => 'string'];
        }

        foreach ($settings as $key => $definition) {
            SystemSetting::set($key, $definition['value'], $definition['type'], 'mail');
        }

        SystemSetting::clearCache();

        return response()->json($this->safeMailSettings() + [
            'message' => 'Configuracion de email actualizada correctamente.',
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        try {
            $this->applyRuntimeMailConfig();

            Mail::raw('Este es un email de prueba de la configuracion de SINOA.', function ($message) use ($validated): void {
                $message->to($validated['test_email'])
                    ->subject('Email de Prueba - SINOA');
            });

            return response()->json([
                'message' => 'Email de prueba enviado correctamente.',
            ]);
        } catch (Throwable $e) {
            Log::warning('API email test failed', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No fue posible enviar el email de prueba.',
            ], 500);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function safeMailSettings(): array
    {
        $password = (string) SystemSetting::get('mail_password', '');

        return [
            'mail_mailer' => SystemSetting::get('mail_mailer', config('mail.default')),
            'mail_host' => SystemSetting::get('mail_host', config('mail.mailers.smtp.host')),
            'mail_port' => SystemSetting::get('mail_port', config('mail.mailers.smtp.port')),
            'mail_username' => SystemSetting::get('mail_username', config('mail.mailers.smtp.username')),
            'mail_encryption' => SystemSetting::get('mail_encryption', config('mail.mailers.smtp.encryption')),
            'mail_from_address' => SystemSetting::get('mail_from_address', config('mail.from.address')),
            'mail_from_name' => SystemSetting::get('mail_from_name', config('mail.from.name')),
            'mail_to' => SystemSetting::get('mail_to', env('MAIL_TO_ALERT')),
            'password_configured' => $password !== '',
        ];
    }

    private function applyRuntimeMailConfig(): void
    {
        $existingMailers = config('mail.mailers', []);
        $smtpBase = $existingMailers['smtp'] ?? ['transport' => 'smtp'];

        config([
            'mail.default' => SystemSetting::get('mail_mailer', config('mail.default', 'log')),
            'mail.from' => [
                'address' => SystemSetting::get('mail_from_address', config('mail.from.address')),
                'name' => SystemSetting::get('mail_from_name', config('mail.from.name')),
            ],
            'mail.mailers.smtp' => array_merge($smtpBase, [
                'host' => SystemSetting::get('mail_host', $smtpBase['host'] ?? config('mail.mailers.smtp.host')),
                'port' => SystemSetting::get('mail_port', $smtpBase['port'] ?? config('mail.mailers.smtp.port')),
                'username' => SystemSetting::get('mail_username', $smtpBase['username'] ?? config('mail.mailers.smtp.username')),
                'password' => SystemSetting::get('mail_password', $smtpBase['password'] ?? ''),
                'encryption' => SystemSetting::get('mail_encryption', $smtpBase['encryption'] ?? config('mail.mailers.smtp.encryption')),
            ]),
        ]);
    }
}
