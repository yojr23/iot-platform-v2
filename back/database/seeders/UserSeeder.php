<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superadminRole = Role::where('code', 'superadmin')->first();
        $adminRole = Role::where('code', 'admin')->first();
        $userRole = Role::where('code', 'user')->first();

        $superadmin = User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role_id' => $superadminRole?->id,
            ]
        );
        $superadmin->forceFill(['is_admin' => true])->save();

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role_id' => $adminRole?->id,
            ]
        );
        $admin->forceFill(['is_admin' => true])->save();

        User::updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
                'role_id' => $userRole?->id,
            ]
        );
    }
}
