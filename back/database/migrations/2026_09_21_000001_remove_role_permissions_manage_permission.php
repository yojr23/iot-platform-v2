<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('code', 'role.permissions.manage')
            ->delete();
    }

    public function down(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['code' => 'role.permissions.manage'],
            [
                'resource' => 'role',
                'action' => 'permissions.manage',
                'description' => 'Manage role permissions',
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('code', 'role.permissions.manage')
            ->value('id');

        $superadminId = DB::table('roles')
            ->where('code', 'superadmin')
            ->value('id');

        if ($permissionId !== null && $superadminId !== null) {
            DB::table('role_permissions')->updateOrInsert(
                [
                    'role_id' => $superadminId,
                    'permission_id' => $permissionId,
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
};
