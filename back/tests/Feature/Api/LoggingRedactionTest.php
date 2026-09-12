<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\Sensor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * SEC-LOG-001/SEC-LOG-002: no secret fragments (token prefixes, API-key length/presence
 * fingerprints) and no raw exception messages (which can carry bound SQL) in application logs.
 */
class LoggingRedactionTest extends TestCase
{
    use RefreshDatabase;

    private const INGESTION_TOKEN_SENTINEL = 'INGESTION_TOKEN_SENTINEL';

    private const DEVICE_KEY_SENTINEL = 'DEVICE_KEY_SENTINEL';

    /** @var array<int,MessageLogged> */
    private array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->captured = [];
        Log::listen(function (MessageLogged $event): void {
            $this->captured[] = $event;
        });
    }

    public function test_rejected_ingestion_token_is_not_logged(): void
    {
        config(['app.ingestion_service_token' => 'the-real-token']);

        $this->withHeaders([
            'X-Ingestion-Token' => self::INGESTION_TOKEN_SENTINEL,
        ])->postJson('/api/ingestion/events', [
            'payload' => ['sensors' => []],
        ])->assertStatus(401);

        $this->assertNoCapturedLogLeaks(self::INGESTION_TOKEN_SENTINEL);
        $this->assertNoCapturedLogLeaks(substr(self::INGESTION_TOKEN_SENTINEL, 0, 4));
        $this->assertNoCapturedLogKey('token_prefix');
    }

    public function test_invalid_device_api_key_rejection_does_not_log_key_material_or_fingerprint(): void
    {
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);

        config(['app.api_key' => 'valid-key']);

        $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 21.5,
            'api_key' => self::DEVICE_KEY_SENTINEL,
        ])->assertStatus(401);

        $rejectionLogs = array_filter(
            $this->captured,
            fn (MessageLogged $e) => $e->message === 'Sensor ingestion rejected: invalid API key'
        );

        $this->assertNotEmpty($rejectionLogs, 'Expected the invalid API key rejection to be logged.');

        foreach ($rejectionLogs as $event) {
            $encoded = json_encode($event->context);
            $this->assertStringNotContainsString(self::DEVICE_KEY_SENTINEL, $encoded);
            $this->assertArrayNotHasKey('provided_api_key_length', $event->context);
            $this->assertArrayNotHasKey('configured_api_key_present', $event->context);
            $this->assertArrayNotHasKey('device_api_key_present', $event->context);
        }
    }

    private function assertNoCapturedLogLeaks(string $needle): void
    {
        foreach ($this->captured as $event) {
            $encoded = json_encode(['message' => $event->message, 'context' => $event->context]);
            $this->assertStringNotContainsString($needle, $encoded, "Log leaked secret material: {$event->message}");
        }
    }

    private function assertNoCapturedLogKey(string $key): void
    {
        foreach ($this->captured as $event) {
            $this->assertArrayNotHasKey($key, $event->context, "Log context still carries forbidden key '{$key}' in: {$event->message}");
        }
    }
}
