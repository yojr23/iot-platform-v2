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
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
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
        });
    }
}
