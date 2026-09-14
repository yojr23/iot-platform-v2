<?php

namespace App\Services\Security;

use App\Models\SystemSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

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
    /**
     * Keys that this service is allowed to encrypt/decrypt.
     * Enforced to prevent accidental encryption of non-secret settings.
     */
    private const ALLOWED_KEYS = ['mail_password'];

    public function put(string $key, string $plaintext, string $group): void
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            throw new \InvalidArgumentException("SecretSettingService: key '{$key}' is not in the allowed encryption allowlist.");
        }

        SystemSetting::set($key, Crypt::encryptString($plaintext), 'string', $group);
    }

    public function get(string $key, string $default = ''): string
    {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            throw new \InvalidArgumentException("SecretSettingService: key '{$key}' is not in the allowed encryption allowlist.");
        }

        $raw = (string) SystemSetting::get($key, '');

        if ($raw === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException $e) {
            Log::error('SecretSettingService: failed to decrypt value', [
                'key' => $key,
                'exception' => $e->getMessage(),
            ]);

            return $default;
        }
    }
}
