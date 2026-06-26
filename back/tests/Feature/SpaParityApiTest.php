<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceType;
use App\Models\Lab;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SensorType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SpaParityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_catalogs_with_json_crud(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $labResponse = $this->actingAs($admin)->postJson('/api/labs', [
            'name' => 'Lab Fermentacion',
            'area' => 'Produccion',
            'process_line' => 'Linea A',
            'description' => 'Control biologico',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Lab Fermentacion');

        $labId = $labResponse->json('data.id');

        $this->actingAs($admin)->getJson('/api/labs')
            ->assertOk()
            ->assertJsonPath('data.0.id', $labId);

        $this->actingAs($admin)->putJson("/api/labs/{$labId}", [
            'name' => 'Lab Calidad',
            'area' => 'QA',
            'process_line' => 'Linea B',
            'description' => null,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Lab Calidad');

        $deviceTypeResponse = $this->actingAs($admin)->postJson('/api/device-types', [
            'name' => 'Gateway Industrial',
            'description' => 'Equipo de borde',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Gateway Industrial');

        $deviceTypeId = $deviceTypeResponse->json('data.id');

        $this->actingAs($admin)->putJson("/api/device-types/{$deviceTypeId}", [
            'name' => 'Gateway Edge',
            'description' => 'Equipo IoT de borde',
        ])->assertOk()
            ->assertJsonPath('data.description', 'Equipo IoT de borde');

        $sensorTypeResponse = $this->actingAs($admin)->postJson('/api/sensor-types', [
            'name' => 'Oxigeno disuelto',
            'unit' => 'mg/L',
            'min_range' => 0,
            'max_range' => 20,
        ])->assertCreated()
            ->assertJsonPath('data.unit', 'mg/L');

        $sensorTypeId = $sensorTypeResponse->json('data.id');

        $this->actingAs($admin)->putJson("/api/sensor-types/{$sensorTypeId}", [
            'name' => 'Oxigeno',
            'unit' => 'mg/L',
            'min_range' => 1,
            'max_range' => 18,
        ])->assertOk()
            ->assertJsonPath('data.min_range', 1);

        $this->actingAs($admin)->deleteJson("/api/sensor-types/{$sensorTypeId}")
            ->assertOk()
            ->assertJsonPath('message', 'Tipo de sensor eliminado correctamente.');

        $this->actingAs($admin)->deleteJson("/api/device-types/{$deviceTypeId}")
            ->assertOk()
            ->assertJsonPath('message', 'Tipo de dispositivo eliminado correctamente.');

        $this->actingAs($admin)->deleteJson("/api/labs/{$labId}")
            ->assertOk()
            ->assertJsonPath('message', 'Laboratorio eliminado correctamente.');
    }

    public function test_admin_can_manage_devices_and_sensors_with_json_crud(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $lab = Lab::factory()->create();
        $deviceType = DeviceType::factory()->create();
        $sensorType = SensorType::factory()->create();

        $deviceResponse = $this->actingAs($admin)->postJson('/api/devices', [
            'name' => 'Nodo Biorreactor',
            'serial_number' => 'SPA-DEVICE-001',
            'device_type_id' => $deviceType->id,
            'lab_id' => $lab->id,
            'ip_address' => '192.168.10.20',
            'mac_address' => '00:1A:2B:3C:4D:5E',
            'status' => true,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Nodo Biorreactor')
            ->assertJsonPath('data.device_type.id', $deviceType->id)
            ->assertJsonPath('data.lab.id', $lab->id);

        $deviceId = $deviceResponse->json('data.id');

        $this->actingAs($admin)->putJson("/api/devices/{$deviceId}", [
            'name' => 'Nodo Reactor Actualizado',
            'serial_number' => 'SPA-DEVICE-001',
            'device_type_id' => $deviceType->id,
            'lab_id' => $lab->id,
            'ip_address' => '192.168.10.21',
            'mac_address' => '00:1A:2B:3C:4D:5F',
            'status' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Nodo Reactor Actualizado')
            ->assertJsonPath('data.status', true)
            ->assertJsonPath('data.is_active', true);

        $sensorResponse = $this->actingAs($admin)->postJson('/api/sensors', [
            'name' => 'Temperatura Reactor',
            'device_id' => $deviceId,
            'sensor_type_id' => $sensorType->id,
            'status' => true,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Temperatura Reactor')
            ->assertJsonPath('data.device.id', $deviceId)
            ->assertJsonPath('data.sensor_type.id', $sensorType->id);

        $sensorId = $sensorResponse->json('data.id');

        $this->actingAs($admin)->putJson("/api/sensors/{$sensorId}", [
            'name' => 'Temperatura Reactor Principal',
            'device_id' => $deviceId,
            'sensor_type_id' => $sensorType->id,
            'status' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Temperatura Reactor Principal')
            ->assertJsonPath('data.status', false);

        $this->actingAs($admin)->deleteJson("/api/sensors/{$sensorId}")
            ->assertOk()
            ->assertJsonPath('message', 'Sensor eliminado correctamente.');

        $this->actingAs($admin)->deleteJson("/api/devices/{$deviceId}")
            ->assertOk()
            ->assertJsonPath('message', 'Dispositivo eliminado correctamente.');
    }

    public function test_sensor_readings_can_be_filtered_and_exported_as_json(): void
    {
        Carbon::setTestNow('2026-05-13 12:00:00');

        try {
            $user = User::factory()->create();
            $sensor = Sensor::factory()->create();

            SensorReading::factory()->create([
                'sensor_id' => $sensor->id,
                'value' => 10,
                'reading_time' => '2026-05-01 08:00:00',
            ]);
            $inside = SensorReading::factory()->create([
                'sensor_id' => $sensor->id,
                'value' => 20,
                'reading_time' => '2026-05-03 08:00:00',
            ]);
            SensorReading::factory()->create([
                'sensor_id' => $sensor->id,
                'value' => 30,
                'reading_time' => '2026-05-10 08:00:00',
            ]);

            $this->actingAs($user)->getJson("/api/sensors/{$sensor->id}/readings?from=2026-05-02&to=2026-05-05")
                ->assertOk()
                ->assertJsonCount(1, 'readings.data')
                ->assertJsonPath('readings.data.0.id', $inside->id);

            $export = $this->actingAs($user)->getJson("/api/sensors/{$sensor->id}/readings/export?from=2026-05-02&to=2026-05-05");

            $export->assertOk()
                ->assertHeader('Content-Disposition')
                ->assertJsonPath('sensor.id', $sensor->id)
                ->assertJsonCount(1, 'readings')
                ->assertJsonPath('readings.0.id', $inside->id);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_can_manage_user_roles_with_json_api(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)->getJson('/api/users')
            ->assertOk()
            ->assertJsonFragment(['id' => $user->id]);

        $this->actingAs($admin)->patchJson("/api/users/{$user->id}/role", [
            'is_admin' => true,
        ])->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.is_admin', true);

        $this->actingAs($admin)->patchJson("/api/users/{$admin->id}/role", [
            'is_admin' => false,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('is_admin');
    }

    public function test_profile_metrics_and_alert_detail_have_json_spa_endpoints(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $alert = Alert::factory()->create(['resolved' => false]);

        $this->actingAs($admin)->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('id', $admin->id)
            ->assertJsonPath('role', 'Administrador');

        $this->actingAs($admin)->getJson("/api/alerts/{$alert->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $alert->id)
            ->assertJsonStructure(['data' => ['id', 'resolved', 'sensor_reading', 'alert_rule', 'sensor', 'device']]);

        $this->actingAs($admin)->getJson('/api/metrics')
            ->assertOk()
            ->assertJsonStructure(['generated_at', 'snapshot']);
    }
}
