<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AlertRule;
use App\Models\Sensor;

class AlertRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ruleTemplates = [
            'Temperatura' => ['min' => 18, 'max' => 28, 'severity' => 'warning', 'message' => 'Temperatura fuera del rango de confort'],
            'Humedad' => ['min' => 35, 'max' => 65, 'severity' => 'info', 'message' => 'Humedad fuera del rango recomendado'],
            'CO2' => ['min' => 400, 'max' => 900, 'severity' => 'warning', 'message' => 'CO₂ elevado detectado'],
            'Monóxido de Carbono' => ['min' => 0, 'max' => 35, 'severity' => 'danger', 'message' => 'Monóxido de carbono peligroso'],
            'Componentes Orgánicos' => ['min' => 0, 'max' => 200, 'severity' => 'warning', 'message' => 'Compuestos orgánicos volátiles elevados'],
            'Humo' => ['min' => 0, 'max' => 5, 'severity' => 'danger', 'message' => 'Partículas de humo detectadas'],
            'Oxígeno' => ['min' => 19, 'max' => 23.5, 'severity' => 'danger', 'message' => 'Oxígeno fuera del rango saludable'],
            'Vibración' => ['min' => 0, 'max' => 1.5, 'severity' => 'danger', 'message' => 'Vibración estructural inusual'],
            'Presión Atmosférica' => ['min' => 985, 'max' => 1030, 'severity' => 'info', 'message' => 'Presión atmosférica fuera de lo normal'],
        ];

        $sensors = Sensor::with(['sensorType', 'device.classroom'])->get();

        foreach ($sensors as $sensor) {
            if (!$sensor->sensorType || !$sensor->device) {
                continue;
            }

            $typeName = $sensor->sensorType->name;
            $template = $ruleTemplates[$typeName] ?? [
                'min' => $sensor->sensorType->min_range,
                'max' => $sensor->sensorType->max_range,
                'severity' => 'info',
                'message' => 'Valores fuera de rango para ' . $typeName,
            ];

            $location = $sensor->device->classroom->name ?? $sensor->device->name;

            AlertRule::updateOrCreate(
                ['sensor_id' => $sensor->id],
                [
                    'sensor_type_id' => $sensor->sensor_type_id,
                    'device_id' => $sensor->device_id,
                    'min_value' => $template['min'],
                    'max_value' => $template['max'],
                    'severity' => $template['severity'],
                    'message' => $template['message'] . ' en ' . $location,
                    'name' => $sensor->name . ' - ' . $sensor->device->name,
                ]
            );
        }
    }
}
