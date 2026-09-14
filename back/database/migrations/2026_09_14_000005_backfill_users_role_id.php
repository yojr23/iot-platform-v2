<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('roles')->upsert([
            ['code' => 'guest', 'level' => 0, 'name' => 'Guest', 'description' => 'Unauthenticated or minimal access', 'assignable' => false, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'user', 'level' => 1, 'name' => 'User', 'description' => 'Standard authenticated user', 'assignable' => true, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'admin', 'level' => 2, 'name' => 'Administrator', 'description' => 'Administrative access with user management', 'assignable' => true, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'superadmin', 'level' => 3, 'name' => 'Super Administrator', 'description' => 'Full system access with role management', 'assignable' => false, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['level', 'name', 'description', 'assignable', 'is_system', 'updated_at']);

        DB::table('users')
            ->where('is_admin', true)
            ->whereNull('role_id')
            ->update(['role_id' => DB::raw('(SELECT id FROM roles WHERE code = \'admin\')')]);

        DB::table('users')
            ->where('is_admin', false)
            ->whereNull('role_id')
            ->update(['role_id' => DB::raw('(SELECT id FROM roles WHERE code = \'user\')')]);
    }

    public function down(): void
    {
        DB::table('users')->update(['role_id' => null]);
    }
};
