<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DomainEventOutbox;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class SensorApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_reading_returns_unauthorized_when_api_key_is_invalid(): void
    {
        $device = Device::factory()->create([
            'status' => true,
            'is_active' => true,
        ]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);
        $this->assertTrue((bool) $sensor->device()->first()->is_active);

        config(['app.api_key' => 'valid-key']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 21.5,
            'api_key' => 'invalid-key',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Unauthorized');

        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_store_reading_returns_forbidden_when_device_is_inactive(): void
    {
        $device = Device::factory()->create([
            'status' => false,
            'is_active' => false,
        ]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);
        $this->assertFalse((bool) $sensor->device()->first()->is_active);

        config(['app.api_key' => 'valid-key']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 25.3,
            'api_key' => 'valid-key',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error', 'Device Inactive');

        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_store_reading_returns_forbidden_when_device_status_is_false_even_if_is_active_true(): void
    {
        $device = Device::factory()->create([
            'status' => false,
            'is_active' => true,
        ]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);

        config(['app.api_key' => 'valid-key']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 15.6,
            'api_key' => 'valid-key',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error', 'Device Inactive');

        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_store_reading_creates_record_when_payload_is_valid(): void
    {
        $device = Device::factory()->create([
            'status' => true,
            'is_active' => true,
        ]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);
        $this->assertTrue((bool) $sensor->device()->first()->is_active);

        config(['app.api_key' => 'valid-key']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 42.75,
            'reading_time' => '2026-01-15 12:30:00',
            'api_key' => 'valid-key',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Reading saved successfully');

        $reading = SensorReading::query()->where('sensor_id', $sensor->id)->first();

        $this->assertNotNull($reading);
        $this->assertEqualsWithDelta(42.75, (float) $reading->value, 0.0001);
        $this->assertSame('2026-01-15 12:30:00', $reading->reading_time->format('Y-m-d H:i:s'));
    }

    public function test_store_reading_replaces_a_stale_cached_latest_reading_and_records_one_domain_fact(): void
    {
        $device = Device::factory()->create(['status' => true, 'is_active' => true]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);
        $oldReading = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 10.0,
            'reading_time' => now()->subMinute(),
        ]);
        $redis = new class
        {
            public array $lists = [];

            public function pipeline(callable $callback): void
            {
                $callback($this);
            }

            public function lpush(string $key, string $value): void
            {
                $this->lists[$key] ??= [];
                array_unshift($this->lists[$key], $value);
            }

            public function ltrim(string $key, int $start, int $stop): void
            {
                $this->lists[$key] = array_slice($this->lists[$key] ?? [], $start, $stop - $start + 1);
            }

            public function lrange(string $key, int $start, int $stop): array
            {
                return array_slice($this->lists[$key] ?? [], $start, $stop - $start + 1);
            }

            public function del(string $key): void
            {
                unset($this->lists[$key]);
            }
        };
        $key = "sensor:latest_readings:{$sensor->id}";
        $redis->lists[$key] = [];
        $redis->lpush($key, json_encode([
            'id' => $oldReading->id,
            'value' => 10.0,
            'reading_time' => $oldReading->reading_time->toIso8601String(),
            'created_at' => $oldReading->created_at->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        $originalRedis = Redis::getFacadeRoot();
        Redis::swap($redis);

        try {
            config(['app.api_key' => 'valid-key']);

            $this->postJson("/api/sensors/{$sensor->id}/readings", [
                'value' => 42.75,
                'reading_time' => now()->format('Y-m-d H:i:s'),
                'api_key' => 'valid-key',
            ])->assertCreated();

            $newReading = SensorReading::query()
                ->where('sensor_id', $sensor->id)
                ->where('value', 42.75)
                ->sole();

            $this->assertSame(1, DomainEventOutbox::query()
                ->where('event_type', 'sensor.reading.created')
                ->where('aggregate_id', (string) $newReading->id)
                ->count());

            $this->getJson("/api/sensors/{$sensor->id}/latest-readings?limit=1")
                ->assertOk()
                ->assertJsonPath('0.id', $newReading->id);
        } finally {
            Redis::swap($originalRedis);
        }
    }

    public function test_store_reading_returns_unauthorized_when_configured_api_key_is_empty(): void
    {
        $device = Device::factory()->create([
            'status' => true,
            'is_active' => true,
        ]);
        $sensor = Sensor::factory()->create(['device_id' => $device->id]);

        config(['app.api_key' => '']);

        $response = $this->postJson("/api/sensors/{$sensor->id}/readings", [
            'value' => 42.75,
            'api_key' => 'any-key-1',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'Unauthorized');

        $this->assertDatabaseCount('sensor_readings', 0);
    }

    public function test_latest_readings_applies_limit_and_excludes_future_records(): void
    {
        Carbon::setTestNow('2026-04-26 10:00:00');

        try {
            $sensor = Sensor::factory()->create();

            $older = SensorReading::factory()->create([
                'sensor_id' => $sensor->id,
                'value' => 10,
                'reading_time' => Carbon::now()->subMinutes(20),
            ]);

            $newer = SensorReading::factory()->create([
                'sensor_id' => $sensor->id,
                'value' => 20,
                'reading_time' => Carbon::now()->subMinutes(5),
            ]);

            SensorReading::factory()->create([
                'sensor_id' => $sensor->id,
                'value' => 99,
                'reading_time' => Carbon::now()->addHour(),
            ]);

            $user = User::factory()->create();

            $response = $this->actingAs($user)->getJson("/api/sensors/{$sensor->id}/latest-readings?limit=2");

            $response->assertOk()
                ->assertJsonCount(2)
                ->assertJsonPath('0.id', $newer->id)
                ->assertJsonPath('1.id', $older->id);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_latest_readings_repairs_a_non_empty_redis_projection_when_it_lags_the_database(): void
    {
        $sensor = Sensor::factory()->create();
        $staleReading = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 10,
            'reading_time' => now()->subMinutes(5),
        ]);
        $latestReading = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 20,
            'reading_time' => now()->subMinute(),
        ]);
        $redis = new class
        {
            public array $lists = [];

            public function pipeline(callable $callback): void
            {
                $callback($this);
            }

            public function lpush(string $key, string $value): void
            {
                $this->lists[$key] ??= [];
                array_unshift($this->lists[$key], $value);
            }

            public function ltrim(string $key, int $start, int $stop): void
            {
                $this->lists[$key] = array_slice($this->lists[$key] ?? [], $start, $stop - $start + 1);
            }

            public function lrange(string $key, int $start, int $stop): array
            {
                return array_slice($this->lists[$key] ?? [], $start, $stop - $start + 1);
            }

            public function del(string $key): void
            {
                unset($this->lists[$key]);
            }
        };
        $key = "sensor:latest_readings:{$sensor->id}";
        $redis->lists[$key] = [];
        $redis->lpush($key, json_encode([
            'id' => $staleReading->id,
            'value' => 10.0,
            'reading_time' => $staleReading->reading_time->toIso8601String(),
            'created_at' => $staleReading->created_at->toIso8601String(),
        ], JSON_THROW_ON_ERROR));

        $originalRedis = Redis::getFacadeRoot();
        Redis::swap($redis);

        try {
            $this->actingAs(User::factory()->create())
                ->getJson("/api/sensors/{$sensor->id}/latest-readings?limit=1")
                ->assertOk()
                ->assertJsonPath('0.id', $latestReading->id);

            $this->assertSame($latestReading->id, json_decode($redis->lists[$key][0], true, 512, JSON_THROW_ON_ERROR)['id']);
        } finally {
            Redis::swap($originalRedis);
        }
    }

    public function test_latest_readings_repairs_an_interior_redis_projection_gap(): void
    {
        $sensor = Sensor::factory()->create();
        $oldest = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 101,
            'reading_time' => now()->subMinutes(3),
        ]);
        $missing = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 102,
            'reading_time' => now()->subMinutes(2),
        ]);
        $latest = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 103,
            'reading_time' => now()->subMinute(),
        ]);
        $redis = new class
        {
            public array $lists = [];

            public function pipeline(callable $callback): void
            {
                $callback($this);
            }

            public function lpush(string $key, string $value): void
            {
                $this->lists[$key] ??= [];
                array_unshift($this->lists[$key], $value);
            }

            public function ltrim(string $key, int $start, int $stop): void
            {
                $this->lists[$key] = array_slice($this->lists[$key] ?? [], $start, $stop - $start + 1);
            }

            public function lrange(string $key, int $start, int $stop): array
            {
                return array_slice($this->lists[$key] ?? [], $start, $stop - $start + 1);
            }

            public function del(string $key): void
            {
                unset($this->lists[$key]);
            }
        };
        $key = "sensor:latest_readings:{$sensor->id}";
        $redis->lists[$key] = [];

        foreach ([$oldest, $latest] as $reading) {
            $redis->lpush($key, json_encode([
                'id' => $reading->id,
                'value' => (float) $reading->value,
                'reading_time' => $reading->reading_time->toIso8601String(),
                'created_at' => $reading->created_at->toIso8601String(),
            ], JSON_THROW_ON_ERROR));
        }

        $originalRedis = Redis::getFacadeRoot();
        Redis::swap($redis);

        try {
            $this->actingAs(User::factory()->create())
                ->getJson("/api/sensors/{$sensor->id}/latest-readings?limit=3")
                ->assertOk()
                ->assertJsonPath('0.id', $latest->id)
                ->assertJsonPath('1.id', $missing->id)
                ->assertJsonPath('2.id', $oldest->id);
        } finally {
            Redis::swap($originalRedis);
        }
    }
}
