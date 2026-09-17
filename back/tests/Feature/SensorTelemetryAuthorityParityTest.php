<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sensor;
use App\Models\User;
use App\Services\Security\ResourceAccessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SEC-RT-002 (Phase C2): the private `sensor.{id}` WebSocket channel carries reading TELEMETRY
 * (App\Events\NewSensorReading: value + reading_time), which is the same data the REST reading
 * endpoints (`/sensors/{s}/readings`, `/series`, `/latest-readings`) gate behind
 * `permission:sensor_reading.view`. Authorizing that channel on `sensor.view` alone let a user
 * with `sensor.view` but WITHOUT `sensor_reading.view` receive live telemetry over WS that REST
 * would deny — a REST/WS authorization divergence. Telemetry authority must be the single rule
 * `canViewSensorReadings()` on BOTH surfaces.
 *
 * Builds the four-cell sensor permission matrix explicitly (view × reading.view).
 */
class SensorTelemetryAuthorityParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => 'test-app-id',
            'broadcasting.connections.pusher.options.cluster' => 'mt1',
            'broadcasting.connections.pusher.options.host' => 'api-mt1.pusher.com',
            'broadcasting.connections.pusher.options.useTLS' => true,
        ]);
        require base_path('routes/channels.php');
    }

    private function userWithPermissions(array $codes): User
    {
        $role = Role::create([
            'code' => 'matrix-'.uniqid(),
            'name' => 'Matrix Role',
            'description' => 'test',
            'is_system' => false,
            'level' => 10,
        ]);
        $permIds = Permission::whereIn('code', $codes)->pluck('id');
        $role->permissions()->sync($permIds);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /** @return array<string,array{0:array<string>,1:bool}> view.reading matrix → telemetry allowed? */
    public static function matrix(): array
    {
        return [
            'view+reading' => [['sensor.view', 'sensor_reading.view'], true],
            'view only' => [['sensor.view'], false],
            // REST /readings gates on sensor_reading.view ALONE (route middleware), so telemetry
            // authority does too — parity, not a stricter WS rule.
            'reading only' => [['sensor_reading.view'], true],
            'neither' => [[], false],
        ];
    }

    /**
     * @dataProvider matrix
     */
    public function test_service_telemetry_authority_matches_reading_permission(array $codes, bool $allowed): void
    {
        $user = $this->userWithPermissions($codes);
        $sensor = Sensor::factory()->create();

        $this->assertSame(
            $allowed,
            app(ResourceAccessService::class)->canViewSensorReadings($user, $sensor),
            'canViewSensorReadings must reflect sensor_reading.view, matching REST reading gate'
        );
    }

    public function test_ws_sensor_channel_denies_telemetry_without_reading_permission(): void
    {
        // Has sensor.view (can see metadata) but NOT sensor_reading.view.
        $user = $this->userWithPermissions(['sensor.view']);
        $sensor = Sensor::factory()->create();
        $token = $user->createToken('t', ['read'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-sensor.'.$sensor->id,
            'socket_id' => '1234.1234',
        ]);

        // The telemetry channel must NOT authorize a user REST would deny reading access.
        $response->assertForbidden();
    }

    public function test_ws_sensor_channel_allows_telemetry_with_reading_permission(): void
    {
        $user = $this->userWithPermissions(['sensor.view', 'sensor_reading.view']);
        $sensor = Sensor::factory()->create();
        $token = $user->createToken('t', ['read'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/broadcasting/auth', [
            'channel_name' => 'private-sensor.'.$sensor->id,
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk()->assertJsonStructure(['auth']);
    }
}
