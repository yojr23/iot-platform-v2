<?php

namespace Tests\Feature\Api;

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
        config(['app.api_key' => 'valid-ingestion-key']);
        Sensor::factory()->count(2)->create();
    }

    public function test_iot_inventory_accepts_the_header_credential(): void
    {
        $this->withHeaders(['X-Device-Key' => 'valid-ingestion-key'])
            ->getJson('/api/iot/sensors')
            ->assertOk()
            ->assertJsonCount(2);
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
}
