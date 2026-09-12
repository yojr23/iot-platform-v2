<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('api-write');
        RateLimiter::clear('auth-login');
    }

    public function test_rotating_api_key_does_not_reset_write_rate_limit(): void
    {
        config(['app.api_key' => 'valid-key']);

        $sensor = Sensor::factory()->create([
            'device_id' => Device::factory()->create([
                'is_active' => true,
                'status' => true,
            ])->id,
        ]);

        // Per-sensor bucket is the tighter one (30/min): send 30 requests,
        // each with a DIFFERENT fake api_key, from the same IP.
        for ($i = 0; $i < 30; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
                ->postJson("/api/sensors/{$sensor->id}/readings", [
                    'value' => 22.5,
                    'api_key' => 'rotating-fake-key-'.$i,
                ]);

            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // Rotating the key again must NOT grant a fresh bucket.
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->postJson("/api/sensors/{$sensor->id}/readings", [
                'value' => 22.5,
                'api_key' => 'rotating-fake-key-final',
            ])
            ->assertStatus(429);
    }

    public function test_rotating_login_email_does_not_bypass_per_ip_login_limit(): void
    {
        $ip = '10.20.30.41';

        for ($i = 0; $i < 5; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/auth/login', [
                    'email' => "attacker{$i}@example.com",
                    'password' => 'wrong-password',
                ]);

            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 6th attempt from the same IP, yet another new email: must be blocked.
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/login', [
                'email' => 'attacker-final@example.com',
                'password' => 'wrong-password',
            ])
            ->assertStatus(429);
    }
}
