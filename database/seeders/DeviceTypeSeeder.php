<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DeviceType;

class DeviceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'Calidad de Ambiente', 'description' => 'Dispositivos para monitorear la calidad del ambiente en el aula'],
            ['name' => 'Pánico', 'description' => 'Dispositivos de emergencia para situaciones de pánico'],
            ['name' => 'Desastres', 'description' => 'Dispositivos para detectar y prevenir desastres'],
            ['name' => 'Sensor Único', 'description' => 'Dispositivo único para pruebas'],
        ];

        foreach ($types as $type) {
            DeviceType::updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
