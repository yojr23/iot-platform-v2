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
            ['name' => 'Temperatura', 'unit' => '°C', 'min_range' => 0, 'max_range' => 60],
            ['name' => 'pH', 'unit' => 'pH', 'min_range' => 0, 'max_range' => 14],
            ['name' => 'Oxigeno Disuelto', 'unit' => 'mg/L', 'min_range' => 0, 'max_range' => 20],
            ['name' => 'Conductividad', 'unit' => 'uS/cm', 'min_range' => 0, 'max_range' => 20000],
            ['name' => 'Turbidez', 'unit' => 'NTU', 'min_range' => 0, 'max_range' => 1000],
            ['name' => 'ORP', 'unit' => 'mV', 'min_range' => -1000, 'max_range' => 1000],
            ['name' => 'Caudal', 'unit' => 'L/min', 'min_range' => 0, 'max_range' => 500],
        ];

        foreach ($sensorTypes as $type) {
            SensorType::create($type);
        }
    }
}
