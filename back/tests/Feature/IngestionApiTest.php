<?php

namespace Tests\Feature;

use App\Models\RawSensorEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class IngestionApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'ingestion-test-token';

    protected function tearDown(): void
    {
        // Blindar: si Mockery::close() lanza (expectativa incumplida), parent::tearDown()
        // igual corre y RefreshDatabase limpia la transacción — evita la cascada
        // "There is already an active transaction" en el resto del suite.
        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
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

    public function test_store_raw_event_accepts_and_persists_source_event_id(): void
    {
        config([
            'app.ingestion_service_token' => self::TOKEN,
        ]);

        $payload = $this->validPayload();
        $payload['source_event_id'] = 'node-01-reading-147';

        $response = $this->withHeaders([
            'X-Ingestion-Token' => self::TOKEN,
        ])->postJson('/api/ingestion/events', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('raw_sensor_events', [
            'id' => $response->json('event_id'),
            'source_event_id' => 'node-01-reading-147',
        ]);
    }

    public function test_store_raw_event_without_source_event_id_still_persists(): void
    {
        config([
            'app.ingestion_service_token' => self::TOKEN,
        ]);

        $response = $this->withHeaders([
            'X-Ingestion-Token' => self::TOKEN,
        ])->postJson('/api/ingestion/events', $this->validPayload());

        $response->assertCreated();

        $event = RawSensorEvent::query()->findOrFail($response->json('event_id'));
        $this->assertNull($event->source_event_id);
    }

    /**
     * Gate 10 (transactional outbox / CDC): el controller ya NO llama a
     * RawSensorEventPublisher::publish() sincrónicamente. Persiste RawSensorEvent + RawEventOutbox
     * en una transacción y la entrega a Redis la maneja Debezium CDC -> cdc:consume-outboxes.
     * Este test congela ese contrato: un ingest crea la fila outbox 'pending' sin dependencia
     * síncrona de Redis.
     */
    public function test_store_raw_event_creates_pending_outbox_row_without_synchronous_redis(): void
    {
        config([
            'app.ingestion_service_token' => self::TOKEN,
        ]);

        $response = $this->withHeaders([
            'X-Ingestion-Token' => self::TOKEN,
        ])->postJson('/api/ingestion/events', $this->validPayload());

        $response->assertCreated();

        $eventId = $response->json('event_id');

        $this->assertDatabaseHas('raw_sensor_events', [
            'id' => $eventId,
            'status' => 'received',
        ]);

        $this->assertDatabaseHas('raw_event_outboxes', [
            'raw_sensor_event_id' => $eventId,
            'status' => 'pending',
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
