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
            ['name' => 'Reactor', 'description' => 'Equipos asociados a reactores y tanques de proceso'],
            ['name' => 'Bomba', 'description' => 'Equipos de bombeo y recirculacion'],
            ['name' => 'Controlador', 'description' => 'Controladores y PLC de procesos quimicos'],
            ['name' => 'Analizador', 'description' => 'Analizadores en linea de variables de proceso'],
            ['name' => 'Estacion de Monitoreo', 'description' => 'Nodos de monitoreo multiparametro'],
        ];

        foreach ($types as $type) {
            DeviceType::updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
