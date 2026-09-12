<?php

namespace App\Services\Security;

use App\Models\SystemSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * SEC-SECRET-001: the single place that encrypts/decrypts values stored via `SystemSetting`
 * that must never sit in the database as plaintext (currently `mail_password` only).
 *
 * Existing code reused: `App\Models\SystemSetting::set()`/`get()` (cache + persistence stay
 * owned there; this service only wraps the value with `Crypt::encryptString`/`decryptString`
 * before it reaches that storage layer).
 * Old owner retired/delegated: `EmailConfigController` no longer calls
 * `SystemSetting::set('mail_password', ...)` with the plaintext directly — it delegates to
 * this service. `SystemSetting::get()`/`getByGroup()` are untouched and keep returning the
 * encrypted blob as-is for any other caller; this service is the only decrypt path.
 * Compatibility window: none — this is a narrow, explicit allowlist of secret keys, not a
 * generic "decrypt every setting" mechanism. Do not route other settings through this class.
 */
class SecretSettingService
{
    public function put(string $key, string $plaintext, string $group): void
    {
        SystemSetting::set($key, Crypt::encryptString($plaintext), 'string', $group);
    }

    public function get(string $key, string $default = ''): string
    {
        $raw = (string) SystemSetting::get($key, '');

        if ($raw === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException) {
            return $default;
        }
    }
}
