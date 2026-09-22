<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
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
        RateLimiter::clear('auth-register');
        RateLimiter::clear('ingestion-events');

        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);
    }

    protected function tearDown(): void
    {
        $this->restoreDefaultLimiters();

        parent::tearDown();
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

    public function test_registration_remains_available_after_the_login_ip_ceiling_is_exhausted(): void
    {
        $ip = '10.20.30.42';
        $this->limitAuthLoginToOneAttempt();
        Notification::fake();

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/login', [
                'email' => 'attacker@example.com',
                'password' => 'wrong-password',
            ])
            ->assertUnprocessable();

        // This guards against accidentally sharing the credential-stuffing
        // bucket with the distinct registration-abuse boundary.
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/register', $this->registrationPayload('independent-register@gmail.com'))
            ->assertCreated();
    }

    public function test_registration_route_enforces_the_dedicated_registration_limiter(): void
    {
        $ip = '10.20.30.43';
        $this->disableAuthLoginLimiter();
        $this->limitAuthRegisterToOneAttempt();
        Notification::fake();

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/register', $this->registrationPayload('register-one@gmail.com'))
            ->assertCreated();

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/register', $this->registrationPayload('register-two@gmail.com'))
            ->assertStatus(429);
    }

    public function test_canonical_ingestion_is_not_blocked_by_an_exhausted_legacy_api_write_bucket(): void
    {
        $ip = '10.20.30.44';
        $this->limitApiWriteToOneAttempt();
        config([
            'app.api_key' => 'legacy-write-key',
            'app.iot_legacy_global_key_fallback_enabled' => true,
            'app.ingestion_service_token' => 'ingestion-test-token',
        ]);

        $sensor = Sensor::factory()->create([
            'device_id' => Device::factory()->create([
                'is_active' => true,
                'status' => true,
            ])->id,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson("/api/sensors/{$sensor->id}/readings", [
                'value' => 22.5,
                'api_key' => 'legacy-write-key',
            ])
            ->assertCreated();

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('X-Ingestion-Token', 'ingestion-test-token')
            ->postJson('/api/ingestion/events', $this->ingestionPayload('node-one'))
            ->assertCreated();
    }

    public function test_canonical_ingestion_uses_an_ip_bucket_not_a_missing_sensor_route_parameter(): void
    {
        $ip = '10.20.30.45';
        $this->disableApiWriteLimiter();
        config([
            'app.ingestion_events_rate_limit_per_minute' => 1,
            'app.ingestion_service_token' => 'ingestion-test-token-one',
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders([
                'X-Ingestion-Token' => 'ingestion-test-token-one',
                'X-Client-Bucket' => 'first-header-value',
            ])
            ->postJson('/api/ingestion/events', $this->ingestionPayload('node-one'))
            ->assertCreated();

        config(['app.ingestion_service_token' => 'ingestion-test-token-two']);

        // A changed body node ID, valid credential, and arbitrary header must
        // not create a fresh bucket. The actual registered limiter must key
        // solely on the server-observed request IP.
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders([
                'X-Ingestion-Token' => 'ingestion-test-token-two',
                'X-Client-Bucket' => 'second-header-value',
            ])
            ->postJson('/api/ingestion/events', $this->ingestionPayload('node-two'))
            ->assertStatus(429);

        config(['app.ingestion_service_token' => 'ingestion-test-token-three']);

        // A separate IP must receive its own bucket. This catches a fallback
        // to the route's absent `{sensor}` parameter, which would make all
        // canonical clients share one empty-sensor bucket.
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.46'])
            ->withHeaders([
                'X-Ingestion-Token' => 'ingestion-test-token-three',
                'X-Client-Bucket' => 'third-header-value',
            ])
            ->postJson('/api/ingestion/events', $this->ingestionPayload('node-three'))
            ->assertCreated();
    }

    private function limitAuthLoginToOneAttempt(): void
    {
        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(1)
            ->by('test-auth-login:ip:'.$request->ip()));
    }

    private function disableAuthLoginLimiter(): void
    {
        RateLimiter::for('auth-login', fn () => Limit::none());
    }

    private function limitAuthRegisterToOneAttempt(): void
    {
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(1)
            ->by('test-auth-register:ip:'.$request->ip()));
    }

    private function limitApiWriteToOneAttempt(): void
    {
        RateLimiter::for('api-write', fn (Request $request) => Limit::perMinute(1)
            ->by('test-api-write:ip:'.$request->ip()));
    }

    private function disableApiWriteLimiter(): void
    {
        RateLimiter::for('api-write', fn () => Limit::none());
    }

    private function restoreDefaultLimiters(): void
    {
        RateLimiter::for('api-write', function (Request $request) {
            $ip = $request->ip();
            $sensor = $request->route('sensor');
            $sensorId = is_object($sensor) && method_exists($sensor, 'getKey')
                ? $sensor->getKey()
                : (string) $sensor;

            return [
                Limit::perMinute(60)->by('iot-write:ip:'.$ip),
                Limit::perMinute(30)->by('iot-write:ip:'.$ip.':sensor:'.$sensorId),
            ];
        });

        RateLimiter::for('auth-login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));
            $ip = $request->ip();

            return [
                Limit::perMinute(5)->by('login:ip:'.$ip),
                Limit::perMinute(5)->by('login:email:'.$email.':ip:'.$ip),
            ];
        });

        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perMinute(5)->by('register:ip:'.$request->ip());
        });

    }

    /**
     * @return array<string, string>
     */
    private function registrationPayload(string $email): array
    {
        return [
            'name' => 'Rate Limit Registration',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ingestionPayload(string $nodeId): array
    {
        return [
            'topic' => 'iot/'.$nodeId.'/readings',
            'received_at' => '2026-09-22T12:00:00Z',
            'payload' => [
                'device' => ['node_id' => $nodeId],
                'timestamp' => '2026-09-22T12:00:00Z',
                'sensors' => [
                    'temperature' => [
                        'value' => 23.47,
                        'unit' => 'C',
                    ],
                ],
            ],
        ];
    }
}
