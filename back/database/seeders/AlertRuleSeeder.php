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
            'Temperatura' => ['min' => 15, 'max' => 35, 'severity' => 'warning', 'message' => 'Temperatura fuera del rango operacional'],
            'pH' => ['min' => 6.5, 'max' => 8.5, 'severity' => 'warning', 'message' => 'pH fuera del rango objetivo'],
            'Oxigeno Disuelto' => ['min' => 2, 'max' => 10, 'severity' => 'danger', 'message' => 'Oxigeno disuelto fuera de rango critico'],
            'Conductividad' => ['min' => 200, 'max' => 15000, 'severity' => 'info', 'message' => 'Conductividad fuera del rango esperado'],
            'Turbidez' => ['min' => 0, 'max' => 200, 'severity' => 'warning', 'message' => 'Turbidez elevada'],
            'ORP' => ['min' => -100, 'max' => 400, 'severity' => 'info', 'message' => 'ORP fuera del rango esperado'],
            'Caudal' => ['min' => 5, 'max' => 250, 'severity' => 'warning', 'message' => 'Caudal fuera del rango operativo'],
        ];

        $sensors = Sensor::with(['sensorType', 'device.lab'])->get();

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

            $location = $sensor->device->lab->name ?? $sensor->device->name;

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
