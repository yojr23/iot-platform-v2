<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\Device;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardGraphCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Task 6 decision: readable by sensor.view OR device.view, 403 for neither. Builds a role with
     * exactly the given permission codes (mirrors the pattern already used in
     * AllReadingsGlobalBoundAboveCeilingTest::token()) so each case is isolated from the seeded
     * default 'user' role, which already carries both permissions.
     */
    private function userWithPermissions(array $permissionCodes): User
    {
        $role = Role::create([
            'code' => 'catalog-test-'.uniqid(),
            'name' => 'Catalog Test Role',
            'description' => 'test',
            'is_system' => false,
            'level' => 10,
        ]);

        if ($permissionCodes !== []) {
            $permIds = Permission::whereIn('code', $permissionCodes)->pluck('id');
            $role->permissions()->sync($permIds);
        }

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_graph_catalog_requires_sanctum_authentication(): void
    {
        $this->getJson('/api/dashboard/graph-catalog')
            ->assertUnauthorized();
    }

    /**
     * PENDING CI EXECUTION — written on Windows, executed by GitHub Actions.
     *
     * Task 6: a user with neither sensor.view nor device.view must be rejected. Previously this
     * endpoint required only auth:sanctum+verified with no resource-capability check at all.
     */
    public function test_user_without_sensor_or_device_view_is_forbidden(): void
    {
        $user = $this->userWithPermissions([]);

        $this->actingAs($user)
            ->getJson('/api/dashboard/graph-catalog')
            ->assertForbidden();
    }

    /**
     * PENDING CI EXECUTION — written on Windows, executed by GitHub Actions.
     *
     * Task 6: sensor.view alone is sufficient (OR, not AND).
     */
    public function test_user_with_only_sensor_view_is_allowed(): void
    {
        $user = $this->userWithPermissions(['sensor.view']);

        $this->actingAs($user)
            ->getJson('/api/dashboard/graph-catalog')
            ->assertOk();
    }

    /**
     * PENDING CI EXECUTION — written on Windows, executed by GitHub Actions.
     *
     * Task 6: device.view alone is sufficient (OR, not AND).
     */
    public function test_user_with_only_device_view_is_allowed(): void
    {
        $user = $this->userWithPermissions(['device.view']);

        $this->actingAs($user)
            ->getJson('/api/dashboard/graph-catalog')
            ->assertOk();
    }

    public function test_authenticated_catalog_returns_every_graph_device_and_restricted_sensor_with_a_minimal_projection(): void
    {
        $user = User::factory()->create();
        $type = SensorType::factory()->create(['unit' => '°C']);
        $devices = Device::factory()->count(101)->create();

        foreach ($devices as $device) {
            Sensor::factory()->create([
                'device_id' => $device->id,
                'sensor_type_id' => $type->id,
                'public_monitoring_enabled' => false,
            ]);
        }

        $restricted = Sensor::query()->where('device_id', $devices->first()->id)->sole();
        AlertRule::create([
            'sensor_type_id' => $type->id,
            'min_value' => 10,
            'max_value' => null,
            'severity' => 'danger',
            'message' => 'Private graph rule',
            'name' => 'Private graph rule',
        ]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/graph-catalog');

        $response->assertOk()
            ->assertJsonPath('version', 1)
            ->assertJsonPath('default_sensor_id', $restricted->id)
            ->assertJsonCount(101, 'devices')
            ->assertJsonPath('devices.0.id', $devices->first()->id)
            ->assertJsonPath('devices.0.name', $devices->first()->name)
            ->assertJsonPath('devices.0.sensors.0.id', $restricted->id)
            ->assertJsonPath('devices.0.sensors.0.name', $restricted->name)
            ->assertJsonPath('devices.0.sensors.0.unit', '°C')
            ->assertJsonPath('devices.0.sensors.0.bands', [
                ['from' => null, 'to' => 10.0, 'severity' => 'danger'],
                ['from' => 10.0, 'to' => null, 'severity' => 'normal'],
            ])
            ->assertJsonPath('devices.0.sensors.0.boundaries', [
                ['value' => 10.0, 'severity' => 'danger', 'bound' => 'min'],
            ]);

        $body = json_encode($response->json());
        $this->assertStringNotContainsStringIgnoringCase('api_key', $body);
        $this->assertStringNotContainsStringIgnoringCase('serial_number', $body);
        $this->assertStringNotContainsStringIgnoringCase('ip_address', $body);
        $this->assertStringNotContainsStringIgnoringCase('mac_address', $body);
        $this->assertStringNotContainsStringIgnoringCase('status', $body);
        $this->assertStringNotContainsStringIgnoringCase('public_monitoring_enabled', $body);
        $this->assertStringNotContainsStringIgnoringCase('sensor_type_id', $body);
        $this->assertStringNotContainsStringIgnoringCase('rule_id', $body);
    }

    public function test_catalog_loads_alert_rules_once_for_all_sensors(): void
    {
        $user = User::factory()->create();
        $type = SensorType::factory()->create();

        foreach (range(1, 3) as $index) {
            $device = Device::factory()->create();
            Sensor::factory()->create([
                'device_id' => $device->id,
                'sensor_type_id' => $type->id,
            ]);
        }

        AlertRule::create([
            'sensor_type_id' => $type->id,
            'max_value' => 30,
            'severity' => 'danger',
            'message' => 'Hot',
            'name' => 'Hot',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->actingAs($user)->getJson('/api/dashboard/graph-catalog')->assertOk();
            $alertRuleQueries = collect(DB::getQueryLog())
                ->filter(fn (array $query): bool => str_contains($query['query'], 'alert_rules'));

            $this->assertCount(1, $alertRuleQueries);
        } finally {
            DB::disableQueryLog();
        }
    }
}
