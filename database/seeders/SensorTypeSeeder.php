<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SensorType;

class SensorTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sensorTypes = [
            ['name' => 'Temperatura', 'unit' => '°C', 'min_range' => -50, 'max_range' => 100],
            ['name' => 'Humedad', 'unit' => '%', 'min_range' => 0, 'max_range' => 100],
            ['name' => 'CO2', 'unit' => 'ppm', 'min_range' => 0, 'max_range' => 5000],
            ['name' => 'Monóxido de Carbono', 'unit' => 'ppm', 'min_range' => 0, 'max_range' => 1000],
            ['name' => 'Componentes Orgánicos', 'unit' => 'ppm', 'min_range' => 0, 'max_range' => 500],
            ['name' => 'Humo', 'unit' => 'ppm', 'min_range' => 0, 'max_range' => 1000],
            ['name' => 'Oxígeno', 'unit' => '%', 'min_range' => 0, 'max_range' => 25],
            ['name' => 'Vibración', 'unit' => 'g', 'min_range' => 0, 'max_range' => 16],
            ['name' => 'Sensor Único', 'unit' => 'unit', 'min_range' => 0, 'max_range' => 100],
            ['name' => 'Presión Atmosférica', 'unit' => 'hPa', 'min_range' => 900, 'max_range' => 1100],
        ];

        foreach ($sensorTypes as $type) {
            SensorType::create($type);
        }
    }
}
