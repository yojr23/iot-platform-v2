<?php

namespace Tests\Unit;

use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSeederSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_accounts_are_not_seeded_in_production(): void
    {
        (new RolePermissionSeeder())->run();
        $originalEnvironment = app()->environment();
        app()->instance('env', 'production');

        try {
            (new UserSeeder())->run();
        } finally {
            app()->instance('env', $originalEnvironment);
        }

        $this->assertDatabaseMissing('users', ['email' => 'superadmin@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'demo@example.com']);
    }
}
