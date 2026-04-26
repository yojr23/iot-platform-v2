<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Sensor;
use App\Models\SensorType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertRuleValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_requires_at_least_one_threshold_value(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $sensorType = SensorType::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('alert-rules.create'))
            ->post(route('alert-rules.store'), [
                'sensor_type_id' => $sensorType->id,
                'severity' => 'warning',
                'message' => 'Regla inválida',
                'min_value' => null,
                'max_value' => null,
            ]);

        $response->assertRedirect(route('alert-rules.create'));
        $response->assertSessionHasErrors(['min_value']);
        $this->assertDatabaseCount('alert_rules', 0);
    }

    public function test_store_rejects_sensor_that_does_not_belong_to_selected_device(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $sensor = Sensor::factory()->create();
        $anotherDevice = Device::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('alert-rules.create'))
            ->post(route('alert-rules.store'), [
                'sensor_type_id' => $sensor->sensor_type_id,
                'device_id' => $anotherDevice->id,
                'sensor_id' => $sensor->id,
                'min_value' => 10,
                'max_value' => 20,
                'severity' => 'warning',
                'message' => 'Regla inválida',
            ]);

        $response->assertRedirect(route('alert-rules.create'));
        $response->assertSessionHasErrors(['sensor_id']);
        $this->assertDatabaseCount('alert_rules', 0);
    }

    public function test_store_rejects_mismatched_sensor_type_for_selected_sensor(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $sensor = Sensor::factory()->create();
        $otherType = SensorType::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('alert-rules.create'))
            ->post(route('alert-rules.store'), [
                'sensor_type_id' => $otherType->id,
                'sensor_id' => $sensor->id,
                'min_value' => 10,
                'max_value' => 20,
                'severity' => 'warning',
                'message' => 'Regla inválida',
            ]);

        $response->assertRedirect(route('alert-rules.create'));
        $response->assertSessionHasErrors(['sensor_type_id']);
        $this->assertDatabaseCount('alert_rules', 0);
    }
}
