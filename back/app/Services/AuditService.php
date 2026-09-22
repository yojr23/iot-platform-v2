<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditService
{
    /** @var array<int,string> */
    private const SAFE_SYSTEM_SETTING_KEYS = [
        'app_name',
        'app_url',
        'mail_mailer',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'mail_from_address',
        'mail_from_name',
        'mail_to',
    ];

    /**
     * Log an audit event.
     */
    public function log(
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?int $actorUserId = null,
        ?Request $request = null,
        ?array $metadata = null
    ): void {
        $actorUserId ??= auth()->id();
        $safeMetadata = $this->safeMetadataFor($action, $metadata);

        $inserted = DB::table('audit_logs')->insert([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            // Request IDs are established by trusted middleware. A caller-supplied raw header is
            // untrusted and must never become durable audit data.
            'request_id' => $request?->attributes->get('request_id'),
            'ip_address' => $request?->ip(),
            'metadata' => $safeMetadata !== null ? json_encode($safeMetadata) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $inserted) {
            return;
        }

        // Deliberately a projection rather than a copy of the durable record: no raw IP,
        // credentials, clear-text setting values, or untrusted request header appears here.
        Log::channel('audit')->info('audit_event', [
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'request_id' => $request?->attributes->get('request_id'),
            'metadata' => $safeMetadata,
        ]);
    }

    /**
     * Log a role change event.
     */
    public function logRoleChange(
        int $targetUserId,
        ?string $oldRoleCode,
        string $newRoleCode,
        ?Request $request = null
    ): void {
        $this->log(
            'user.role.changed',
            'user',
            $targetUserId,
            null,
            $request,
            [
                'old_role' => $oldRoleCode,
                'new_role' => $newRoleCode,
            ]
        );
    }

    /**
     * Log a device API key rotation event.
     */
    public function logDeviceApiKeyRotated(
        int $deviceId,
        ?Request $request = null
    ): void {
        $this->log(
            'device.api_key.rotated',
            'device',
            $deviceId,
            null,
            $request
        );
    }

    /**
     * Log a system setting change event.
     */
    public function logSystemSettingChanged(
        string $settingKey,
        ?string $oldValue,
        string $newValue,
        ?Request $request = null
    ): void {
        $this->log(
            'system_setting.changed',
            'system_setting',
            null,
            null,
            $request,
            [
                'setting_key' => $settingKey,
                'value_changed' => true,
            ]
        );
    }

    /**
     * Accept only a deliberately small, event-specific audit projection.
     *
     * @param  array<string,mixed>|null  $metadata
     * @return array<string,mixed>|null
     */
    private function safeMetadataFor(string $action, ?array $metadata): ?array
    {
        if ($action !== 'system_setting.changed') {
            return null;
        }

        $settingKey = $metadata['setting_key'] ?? null;

        if (! is_string($settingKey) || ! in_array($settingKey, self::SAFE_SYSTEM_SETTING_KEYS, true)) {
            return ['value_changed' => true];
        }

        return [
            'setting_key' => $settingKey,
            'value_changed' => true,
        ];
    }
}
