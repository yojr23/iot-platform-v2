<?php

namespace Tests\Feature;

use App\Models\Sensor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->restoreDefaultApiWriteLimiter();

        parent::tearDown();
    }

    public function test_api_write_endpoint_is_rate_limited(): void
    {
        $this->setShortApiWriteLimiter();

        config(['app.api_key' => 'valid-key']);

        $sensor = Sensor::factory()->create([
            'device_id' => \App\Models\Device::factory()->create([
                'is_active' => true,
                'status' => true,
            ])->id,
        ]);

        $payload = [
            'value' => 22.5,
            'api_key' => 'valid-key',
        ];

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->postJson("/api/sensors/{$sensor->id}/readings", $payload)
            ->assertCreated();

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->postJson("/api/sensors/{$sensor->id}/readings", $payload)
            ->assertCreated();

        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->postJson("/api/sensors/{$sensor->id}/readings", $payload)
            ->assertStatus(429);
    }

    private function setShortApiWriteLimiter(): void
    {
        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(2)->by('test-api-write|'.$request->ip());
        });
    }

    private function restoreDefaultApiWriteLimiter(): void
    {
        RateLimiter::for('api-write', function (Request $request) {
            $identifier = $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip();
            $sensor = $request->route('sensor');
            $sensorId = is_object($sensor) && method_exists($sensor, 'getKey')
                ? $sensor->getKey()
                : (string) $sensor;
            $apiKeyFingerprint = substr(hash('sha256', (string) ($request->header('X-Device-Key') ?? $request->input('api_key', ''))), 0, 16);

            return Limit::perMinute(60)->by($identifier.'|sensor:'.$sensorId.'|key:'.$apiKeyFingerprint);
        });
    }
}
