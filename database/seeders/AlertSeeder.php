<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Alert;
use App\Models\SensorReading;
use App\Models\AlertRule;

class AlertSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reading = SensorReading::first(); // Ejemplo: Obtener la primera lectura de sensor
        $rule = AlertRule::first(); // Ejemplo: Obtener la primera regla de alerta

        Alert::create([
            'sensor_reading_id' => $reading->id,
            'alert_rule_id' => $rule->id,
            'resolved' => false,
            'resolved_at' => null
        ]);

        Alert::create([
            'sensor_reading_id' => $reading->id,
            'alert_rule_id' => $rule->id,
            'resolved' => true,
            'resolved_at' => now()
        ]);
    }
}
