<?php

namespace Tests\Feature;

use App\Models\RawSensorEvent;
use App\Services\Ingestion\RawSensorEventPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class IngestionApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'ingestion-test-token';

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_store_raw_event_with_valid_token_persists_event(): void
    {
        config([
            'app.ingestion_service_token' => self::TOKEN,
        ]);

        $response = $this->withHeaders([
            'X-Ingestion-Token' => self::TOKEN,
        ])->postJson('/api/ingestion/events', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('message', 'Raw sensor event stored successfully')
            ->assertJsonPath('status', 'received');

        $eventId = $response->json('event_id');

        $this->assertDatabaseHas('raw_sensor_events', [
            'id' => $eventId,
            'topic' => 'iot/lab_postgrado_nodo_01/readings',
            'node_id' => 'lab_postgrado_nodo_01',
            'status' => 'received',
        ]);
    }

    public function test_store_raw_event_without_token_returns_unauthorized(): void
    {
        config([
            'app.ingestion_service_token' => self::TOKEN,
        ]);

        $this->postJson('/api/ingestion/events', $this->validPayload())
            ->assertStatus(401)
            ->assertJsonPath('error', 'Unauthorized');
    }

    public function test_store_raw_event_with_invalid_token_returns_unauthorized(): void
    {
        config([
            'app.ingestion_service_token' => self::TOKEN,
        ]);

        $this->withHeaders([
            'X-Ingestion-Token' => 'wrong-token',
        ])->postJson('/api/ingestion/events', $this->validPayload())
            ->assertStatus(401)
            ->assertJsonPath('error', 'Unauthorized');
    }

    public function test_store_raw_event_without_sensors_returns_validation_error(): void
    {
        config([
            'app.ingestion_service_token' => self::TOKEN,
        ]);

        $payload = $this->validPayload();
        unset($payload['payload']['sensors']);

        $this->withHeaders([
            'X-Ingestion-Token' => self::TOKEN,
        ])->postJson('/api/ingestion/events', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payload.sensors']);
    }

    public function test_store_raw_event_extracts_node_id_from_payload(): void
    {
        config([
            'app.ingestion_service_token' => self::TOKEN,
        ]);

        $response = $this->withHeaders([
            'X-Ingestion-Token' => self::TOKEN,
        ])->postJson('/api/ingestion/events', $this->validPayload());

        $event = RawSensorEvent::query()->findOrFail($response->json('event_id'));

        $this->assertSame('lab_postgrado_nodo_01', $event->node_id);
    }

    public function test_store_raw_event_attempts_to_publish_after_persisting(): void
    {
        config([
            'app.ingestion_service_token' => self::TOKEN,
        ]);

        $publisher = Mockery::mock(RawSensorEventPublisher::class);
        $publisher
            ->shouldReceive('publish')
            ->once()
            ->withArgs(function (RawSensorEvent $event): bool {
                return $event->id !== null
                    && $event->node_id === 'lab_postgrado_nodo_01'
                    && $event->status === 'received';
            })
            ->andReturn(true);

        $this->app->instance(RawSensorEventPublisher::class, $publisher);

        $this->withHeaders([
            'X-Ingestion-Token' => self::TOKEN,
        ])->postJson('/api/ingestion/events', $this->validPayload())
            ->assertCreated();
    }

    public function test_store_raw_event_does_not_fail_when_redis_publish_throws(): void
    {
        config([
            'app.ingestion_service_token' => self::TOKEN,
        ]);

        Redis::shouldReceive('command')
            ->once()
            ->andThrow(new \RuntimeException('Redis unavailable'));

        $response = $this->withHeaders([
            'X-Ingestion-Token' => self::TOKEN,
        ])->postJson('/api/ingestion/events', $this->validPayload());

        $response->assertCreated();

        $this->assertDatabaseCount('raw_sensor_events', 1);
        $this->assertDatabaseHas('raw_sensor_events', [
            'node_id' => 'lab_postgrado_nodo_01',
            'status' => 'received',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function validPayload(): array
    {
        return [
            'topic' => 'iot/lab_postgrado_nodo_01/readings',
            'received_at' => '2026-05-14T17:30:00Z',
            'payload' => [
                'device' => [
                    'node_id' => 'lab_postgrado_nodo_01',
                    'firmware_version' => '1.0.0',
                    'location' => 'Laboratorio Posgrado Quimica - UNAB',
                ],
                'timestamp' => '2026-05-14T17:30:00Z',
                'session' => [
                    'uptime_ms' => 473821,
                    'boot_count' => 3,
                    'reading_index' => 147,
                ],
                'network' => [
                    'wifi_rssi_dbm' => -67,
                    'mqtt_reconnections' => 0,
                ],
                'sensors' => [
                    'temperature' => [
                        'value' => 23.47,
                        'unit' => 'C',
                        'sensor_model' => 'DS18B20',
                        'status' => 'ok',
                    ],
                    'dissolved_oxygen' => [
                        'value' => 8.21,
                        'unit' => 'mg/L',
                        'sensor_model' => 'Atlas Scientific DO',
                        'status' => 'ok',
                    ],
                ],
                'qc' => [
                    'checksum' => 'a3f9',
                    'valid' => true,
                ],
            ],
        ];
    }
}
