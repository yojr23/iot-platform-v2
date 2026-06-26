<?php

namespace Tests\Feature;

use App\Models\Sensor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IotApiKeyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_iot_sensor_index_rejects_invalid_api_key(): void
    {
        config(['app.api_key' => 'valid-ingestion-key']);

        $this->withHeaders([
            'X-Device-Key' => 'invalid-key',
        ])->getJson('/api/iot/sensors')
            ->assertStatus(401)
            ->assertJsonPath('error', 'Unauthorized');
    }

    public function test_iot_sensor_index_accepts_valid_api_key(): void
    {
        config(['app.api_key' => 'valid-ingestion-key']);

        Sensor::factory()->count(2)->create();

        $this->withHeaders([
            'X-Device-Key' => 'valid-ingestion-key',
        ])->getJson('/api/iot/sensors')
            ->assertOk()
            ->assertJsonCount(2);
    }
}
