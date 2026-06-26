<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_login_returns_bearer_token_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'api-user@example.com',
            'password' => 'password',
            'is_admin' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit-client',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['access_token']);
    }

    public function test_api_me_requires_valid_token(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_api_me_returns_authenticated_user_data_with_bearer_token(): void
    {
        $user = User::factory()->create([
            'email' => 'api-user2@example.com',
            'password' => 'password',
            'is_admin' => false,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'phpunit-client',
        ]);

        $token = $loginResponse->json('access_token');
        $this->assertNotEmpty($token);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.is_admin', false);
    }
}
