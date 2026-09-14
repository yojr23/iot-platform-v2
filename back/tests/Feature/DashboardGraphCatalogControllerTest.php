<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardGraphCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_graph_catalog_requires_sanctum_authentication(): void
    {
        $this->getJson('/api/dashboard/graph-catalog')
            ->assertUnauthorized();
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
