<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $roles = [
            ['code' => 'guest', 'level' => 0, 'name' => 'Guest', 'description' => 'Unauthenticated or minimal access', 'assignable' => false, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'user', 'level' => 1, 'name' => 'User', 'description' => 'Standard authenticated user', 'assignable' => true, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'admin', 'level' => 2, 'name' => 'Administrator', 'description' => 'Administrative access with user management', 'assignable' => true, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'superadmin', 'level' => 3, 'name' => 'Super Administrator', 'description' => 'Full system access with role management', 'assignable' => false, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('roles')->upsert(
            $roles,
            ['code'],
            ['level', 'name', 'description', 'assignable', 'is_system', 'updated_at']
        );

        $permissions = [
            // Device permissions
            ['code' => 'device.view', 'resource' => 'device', 'action' => 'view', 'description' => 'View devices', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'device.create', 'resource' => 'device', 'action' => 'create', 'description' => 'Create devices', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'device.update', 'resource' => 'device', 'action' => 'update', 'description' => 'Update devices', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'device.delete', 'resource' => 'device', 'action' => 'delete', 'description' => 'Delete devices', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'device.api_key.rotate', 'resource' => 'device', 'action' => 'api_key.rotate', 'description' => 'Rotate device API key', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],

            // Sensor permissions
            ['code' => 'sensor.view', 'resource' => 'sensor', 'action' => 'view', 'description' => 'View sensors', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'sensor.create', 'resource' => 'sensor', 'action' => 'create', 'description' => 'Create sensors', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'sensor.update', 'resource' => 'sensor', 'action' => 'update', 'description' => 'Update sensors', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'sensor.delete', 'resource' => 'sensor', 'action' => 'delete', 'description' => 'Delete sensors', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],

            // Sensor reading permissions
            ['code' => 'sensor_reading.view', 'resource' => 'sensor_reading', 'action' => 'view', 'description' => 'View sensor readings', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'sensor_reading.export', 'resource' => 'sensor_reading', 'action' => 'export', 'description' => 'Export sensor readings', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],

            // Alert permissions
            ['code' => 'alert.view', 'resource' => 'alert', 'action' => 'view', 'description' => 'View alerts', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'alert.resolve', 'resource' => 'alert', 'action' => 'resolve', 'description' => 'Resolve alerts', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],

            // Alert rule permissions
            ['code' => 'alert_rule.view', 'resource' => 'alert_rule', 'action' => 'view', 'description' => 'View alert rules', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'alert_rule.create', 'resource' => 'alert_rule', 'action' => 'create', 'description' => 'Create alert rules', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'alert_rule.update', 'resource' => 'alert_rule', 'action' => 'update', 'description' => 'Update alert rules', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'alert_rule.delete', 'resource' => 'alert_rule', 'action' => 'delete', 'description' => 'Delete alert rules', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],

            // User permissions
            ['code' => 'user.view', 'resource' => 'user', 'action' => 'view', 'description' => 'View users', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'user.role.assign', 'resource' => 'user', 'action' => 'role.assign', 'description' => 'Assign user roles', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],

            // Role permissions
            ['code' => 'role.view', 'resource' => 'role', 'action' => 'view', 'description' => 'View roles', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],

            // System settings permissions
            ['code' => 'system_setting.view', 'resource' => 'system_setting', 'action' => 'view', 'description' => 'View system settings', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'system_setting.update', 'resource' => 'system_setting', 'action' => 'update', 'description' => 'Update system settings', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],

            // Public monitoring permissions
            ['code' => 'public_monitoring.manage', 'resource' => 'public_monitoring', 'action' => 'manage', 'description' => 'Manage public monitoring', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],

            // Audit permissions
            ['code' => 'audit.view', 'resource' => 'audit', 'action' => 'view', 'description' => 'View audit logs', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],

            // Dashboard permissions
            ['code' => 'dashboard.view', 'resource' => 'dashboard', 'action' => 'view', 'description' => 'View dashboard', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('permissions')->upsert(
            $permissions,
            ['code'],
            ['resource', 'action', 'description', 'is_system', 'updated_at']
        );

        // Map roles to permissions
        $guestPermissions = ['dashboard.view'];
        $userPermissions = ['dashboard.view', 'device.view', 'sensor.view', 'sensor_reading.view', 'alert.view', 'alert.resolve', 'alert_rule.view'];
        $adminPermissions = array_merge($userPermissions, [
            'device.create', 'device.update', 'device.delete', 'device.api_key.rotate',
            'sensor.create', 'sensor.update', 'sensor.delete',
            'sensor_reading.export',
            'alert.resolve',
            'alert_rule.create', 'alert_rule.update', 'alert_rule.delete',
            'user.view', 'user.role.assign',
            'role.view',
            'system_setting.view', 'system_setting.update',
            'public_monitoring.manage',
            'audit.view',
        ]);
        $superadminPermissions = $adminPermissions;

        $roleIds = DB::table('roles')->pluck('id', 'code');
        $permissionIds = DB::table('permissions')->pluck('id', 'code');
        $rolePermissions = [];
        foreach ($guestPermissions as $permCode) {
            $rolePermissions[] = ['role_id' => $roleIds['guest'], 'permission_id' => $permissionIds[$permCode], 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($userPermissions as $permCode) {
            $rolePermissions[] = ['role_id' => $roleIds['user'], 'permission_id' => $permissionIds[$permCode], 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($adminPermissions as $permCode) {
            $rolePermissions[] = ['role_id' => $roleIds['admin'], 'permission_id' => $permissionIds[$permCode], 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($superadminPermissions as $permCode) {
            $rolePermissions[] = ['role_id' => $roleIds['superadmin'], 'permission_id' => $permissionIds[$permCode], 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table('role_permissions')->upsert(
            $rolePermissions,
            ['role_id', 'permission_id'],
            ['updated_at']
        );
    }
}
