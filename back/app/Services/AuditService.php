<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditService
{
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
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actorUserId ?? auth()->id(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'request_id' => $request?->header('X-Request-Id'),
            'ip_address' => $request?->ip(),
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => now(),
            'updated_at' => now(),
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
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ]
        );
    }
}
