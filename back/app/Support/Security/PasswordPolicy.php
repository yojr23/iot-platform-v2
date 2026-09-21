<?php

namespace App\Support\Security;

use Illuminate\Validation\Rules\Password;

final class PasswordPolicy
{
    /**
     * @return array<int, mixed>
     */
    public static function rules(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised(),
        ];
    }
}
