<?php

namespace App\Http\Requests\Api;

use App\Models\SystemSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateEmailConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string,mixed>
     */
    public function rules(): array
    {
        return [
            'mail_mailer' => ['required', 'string', 'in:smtp,mailgun,postmark,ses,sendmail,log'],
            'mail_host' => ['required', 'string'],
            // SEC-SMTP-001: allowlist standard submission/relay ports so an admin cannot
            // point the relay at an arbitrary internal service port.
            'mail_port' => ['required', 'integer', 'in:25,465,587,2525'],
            'mail_username' => ['required', 'email'],
            'mail_password' => ['nullable', 'string', 'min:8'],
            'mail_encryption' => ['required', 'string', 'in:tls,ssl'],
            'mail_from_address' => ['required', 'email'],
            'mail_from_name' => ['required', 'string', 'max:255'],
            'mail_to' => ['required', 'email'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $existingPassword = (string) SystemSetting::get('mail_password', '');

            if ($existingPassword === '' && ! $this->filled('mail_password')) {
                $validator->errors()->add('mail_password', 'Debes configurar una contrasena SMTP.');
            }

            // SEC-SMTP-001: block the relay from targeting loopback / link-local /
            // metadata endpoints, which would turn admin mail config into an internal
            // network pivot. Static string/IP check only — no live DNS resolution here
            // (a hostname that resolves to a blocked IP at send time is not caught).
            $host = strtolower(trim((string) $this->input('mail_host', '')));

            if ($host !== '' && $this->isBlockedMailHost($host)) {
                $validator->errors()->add('mail_host', 'El host SMTP no esta permitido.');
            }
        });
    }

    private function isBlockedMailHost(string $host): bool
    {
        if (in_array($host, ['localhost', '0.0.0.0', '::1', '[::1]'], true)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Loopback 127.0.0.0/8
            if (str_starts_with($host, '127.')) {
                return true;
            }

            // Link-local / cloud metadata 169.254.0.0/16 (incl. 169.254.169.254)
            if (str_starts_with($host, '169.254.')) {
                return true;
            }
        }

        return false;
    }
}
