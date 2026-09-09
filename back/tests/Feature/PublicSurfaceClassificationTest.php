<?php

namespace Tests\Feature;

use App\Models\Sensor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Freezes the current classification of the backend's anonymous/non-session
 * routes (Pre-Stage-6 preflight, PLAN.md Stage 6.0A). No route is removed or
 * added here; this only documents/locks the existing contract.
 */
class PublicSurfaceClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_iot_sensor_inventory_requires_device_key(): void
    {
        config(['app.api_key' => 'valid-ingestion-key']);

        $response = $this->getJson('/api/iot/sensors');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Unauthorized');
    }

    public function test_iot_sensor_inventory_rejects_wrong_device_key(): void
    {
        config(['app.api_key' => 'valid-ingestion-key']);

        $response = $this->withHeaders([
            'X-Device-Key' => 'wrong-key',
        ])->getJson('/api/iot/sensors');

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Unauthorized');
    }

    public function test_iot_sensor_inventory_accepts_valid_device_key(): void
    {
        config(['app.api_key' => 'valid-ingestion-key']);

        Sensor::factory()->count(2)->create();

        $response = $this->withHeaders([
            'X-Device-Key' => 'valid-ingestion-key',
        ])->getJson('/api/iot/sensors');

        $response->assertOk()
            ->assertJsonCount(2);
    }

    public function test_health_is_infrastructure_only(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();

        $payload = $response->json();

        $this->assertEqualsCanonicalizing(['status', 'app', 'timestamp'], array_keys($payload));

        $forbiddenKeys = ['sensor', 'sensors', 'device', 'devices', 'alert', 'alerts', 'user', 'users', 'reading', 'readings'];
        foreach ($forbiddenKeys as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }
    }
}
