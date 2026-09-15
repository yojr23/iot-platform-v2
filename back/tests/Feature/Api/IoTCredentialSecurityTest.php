<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-IOT-002: the device-facing inventory endpoint must accept its credential only
 * through the X-Device-Key header — never a query string (leaks into logs/proxies/history)
 * and never a request body. Header-only subset of Task 8; per-device hashing + removal of
 * the global legacy key are a separate operator-coordinated release.
 */
class IoTCredentialSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.api_key' => 'valid-ingestion-key',
            'app.iot_legacy_global_key_fallback_enabled' => true,
        ]);
        Sensor::factory()->count(2)->create();
    }

    public function test_iot_inventory_accepts_the_header_credential(): void
    {
        $this->withHeaders(['X-Device-Key' => 'valid-ingestion-key'])
            ->getJson('/api/iot/sensors')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_iot_inventory_returns_only_the_authenticated_devices_sensors(): void
    {
        $device = $this->deviceWithKey('device-one-key');
        $otherDevice = $this->deviceWithKey('device-two-key');
        $ownedSensors = Sensor::factory()->count(2)->create(['device_id' => $device->id]);
        $otherSensor = Sensor::factory()->create(['device_id' => $otherDevice->id]);

        $response = $this->withHeaders(['X-Device-Key' => 'device-one-key'])
            ->getJson('/api/iot/sensors')
            ->assertOk();

        $payload = $response->json();
        $this->assertCount(2, $payload);
        $this->assertSame([$device->id], array_values(array_unique(array_column($payload, 'device_id'))));
        $this->assertNotContains($otherSensor->id, array_column($payload, 'id'));
        $this->assertEqualsCanonicalizing($ownedSensors->pluck('id')->all(), array_column($payload, 'id'));
    }

    public function test_iot_inventory_rejects_the_global_key_when_legacy_fallback_is_disabled(): void
    {
        config(['app.iot_legacy_global_key_fallback_enabled' => false]);

        $this->withHeaders(['X-Device-Key' => 'valid-ingestion-key'])
            ->getJson('/api/iot/sensors')
            ->assertUnauthorized();
    }

    public function test_iot_inventory_rejects_a_query_string_credential(): void
    {
        // Correct key, wrong transport (query string) → unauthorized.
        $this->getJson('/api/iot/sensors?api_key=valid-ingestion-key')
            ->assertStatus(401);
    }

    public function test_iot_inventory_rejects_a_body_credential(): void
    {
        $this->call('GET', '/api/iot/sensors', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode(['api_key' => 'valid-ingestion-key']))
            ->assertStatus(401);
    }

    private function deviceWithKey(string $plaintextKey): Device
    {
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $device->update([
            'api_key_hash' => hash('sha256', $plaintextKey),
            'api_key_prefix' => substr($plaintextKey, 0, 8),
        ]);

        return $device->refresh();
    }
}
