<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * SEC-SECRET-001: one-time data migration encrypting any pre-existing plaintext
 * `system_settings.mail_password` row in place.
 *
 * Existing code reused: `Crypt::encryptString`/`decryptString`, same primitives as the new
 * `App\Services\Security\SecretSettingService` (which owns all future writes/reads of this key).
 * Existing owner retired/delegated: `EmailConfigController::update()` no longer writes this
 * column in plaintext (see SecretSettingService); this migration only backfills rows written by
 * the old code path before that change shipped.
 * Compatibility window: none needed post-deploy — this migration is the cutover itself. Safe to
 * run twice: an already-encrypted value decrypts successfully, so it's left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('system_settings')->where('key', 'mail_password')->first();

        if (! $row || $row->value === null || $row->value === '') {
            return;
        }

        try {
            Crypt::decryptString($row->value);

            return; // already encrypted, nothing to do
        } catch (\Throwable $e) {
            // plaintext - fall through and encrypt
        }

        DB::table('system_settings')
            ->where('key', 'mail_password')
            ->update(['value' => Crypt::encryptString($row->value)]);

        Cache::forget('system_setting_mail_password');
        Cache::forget('system_settings_group_mail');
        Cache::forget('system_settings_groups');
    }

    public function down(): void
    {
        // No-op: irreversible by design, decrypting back to plaintext at rest would
        // reintroduce the vulnerability this migration fixes.
    }
};
