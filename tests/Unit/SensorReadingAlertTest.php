<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\SensorReading;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Sensor;
use App\Models\SensorType;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SensorReadingAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_is_created_when_reading_exceeds_threshold()
    {
        // Crear un tipo de sensor
        $sensorType = SensorType::factory()->create();

        // Crear un sensor asociado al tipo de sensor
        $sensor = Sensor::factory()->create([
            'sensor_type_id' => $sensorType->id,
        ]);

        // Crear una regla de alerta para el sensor
        $alertRule = AlertRule::factory()->create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $sensor->device_id,
            'sensor_id' => $sensor->id,
            'min_value' => -10,
            'max_value' => 45,
        ]);

        // Crear una lectura que exceda el umbral
        $reading = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 50, // Excede el max_value
        ]);

        // Llamar al método para verificar alertas
        $reading->checkForAlert();

        // Verificar que se haya creado una alerta
        $this->assertDatabaseHas('alerts', [
            'sensor_reading_id' => $reading->id,
            'alert_rule_id' => $alertRule->id,
            'resolved' => false,
        ]);
    }

    public function test_no_alert_is_created_when_reading_is_within_threshold()
    {
        // Crear un tipo de sensor
        $sensorType = SensorType::factory()->create();

        // Crear un sensor asociado al tipo de sensor
        $sensor = Sensor::factory()->create([
            'sensor_type_id' => $sensorType->id,
        ]);

        // Crear una regla de alerta para el sensor
        $alertRule = AlertRule::factory()->create([
            'sensor_type_id' => $sensorType->id,
            'device_id' => $sensor->device_id,
            'sensor_id' => $sensor->id,
            'min_value' => -10,
            'max_value' => 45,
        ]);

        // Crear una lectura dentro del umbral
        $reading = SensorReading::factory()->create([
            'sensor_id' => $sensor->id,
            'value' => 20, // Dentro del rango
        ]);

        // Llamar al método para verificar alertas
        $reading->checkForAlert();

        // Verificar que no se haya creado ninguna alerta
        $this->assertDatabaseMissing('alerts', [
            'sensor_reading_id' => $reading->id,
        ]);
    }
}
