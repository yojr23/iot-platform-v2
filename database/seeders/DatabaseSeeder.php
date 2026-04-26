<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\Lab;
use App\Models\Device;
use App\Models\DeviceStatusLog;
use App\Models\DeviceType;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\SensorType;
use App\Models\Alert;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DeviceTypeSeeder::class,
            SensorTypeSeeder::class,
            SystemSettingsSeeder::class,
        ]);

        $this->seedLabs();
        $devices = $this->seedDevices();
        $sensors = $this->seedSensors($devices);

        $this->call([
            AlertRuleSeeder::class,
        ]);

        $this->seedSensorReadings($sensors);
        $this->seedAlerts($sensors);
        $this->seedDeviceStatusLogs($devices);
    }

    private function seedLabs(): void
    {
        $labs = [
            ['name' => 'Reactor Biologico A', 'area' => 'Tratamiento Secundario', 'process_line' => 'Oxigenacion', 'description' => 'Reactor aerobio principal'],
            ['name' => 'Reactor Biologico B', 'area' => 'Tratamiento Secundario', 'process_line' => 'Nitrificacion', 'description' => 'Reactor de soporte'],
            ['name' => 'Tanque de Ajuste pH', 'area' => 'Pretratamiento', 'process_line' => 'Neutralizacion', 'description' => 'Ajuste de pH de entrada'],
            ['name' => 'Clarificador', 'area' => 'Sedimentacion', 'process_line' => 'Clarificacion', 'description' => 'Separacion de solidos'],
            ['name' => 'Laboratorio de Control', 'area' => 'Analitica', 'process_line' => 'Monitoreo', 'description' => 'Validacion de calidad'],
        ];

        foreach ($labs as $lab) {
            Lab::updateOrCreate(['name' => $lab['name']], $lab);
        }
    }

    /**
     * @return array<string,\App\Models\Device>
     */
    private function seedDevices(): array
    {
        $deviceTypeMap = DeviceType::all()->keyBy('name');
        $labMap = Lab::all()->keyBy('name');
        $now = Carbon::now();

        $devices = [
            [
                'name' => 'Nodo Multiparametro Reactor A',
                'serial_number' => 'LAB-REA-001',
                'device_type' => 'Estacion de Monitoreo',
                'lab' => 'Reactor Biologico A',
                'ip_address' => '10.0.0.11',
                'mac_address' => '00:11:22:33:44:01',
                'status' => true,
                'last_communication' => $now->copy()->subMinutes(5),
            ],
            [
                'name' => 'Controlador pH Tanque',
                'serial_number' => 'LAB-PH-001',
                'device_type' => 'Controlador',
                'lab' => 'Tanque de Ajuste pH',
                'ip_address' => '10.0.0.12',
                'mac_address' => '00:11:22:33:44:02',
                'status' => true,
                'last_communication' => $now->copy()->subMinutes(15),
            ],
            [
                'name' => 'Analizador Reactor B',
                'serial_number' => 'LAB-REA-002',
                'device_type' => 'Analizador',
                'lab' => 'Reactor Biologico B',
                'ip_address' => '10.0.1.11',
                'mac_address' => '00:11:22:33:44:03',
                'status' => true,
                'last_communication' => $now->copy()->subMinutes(7),
            ],
            [
                'name' => 'Bomba Recirculacion',
                'serial_number' => 'LAB-BOM-001',
                'device_type' => 'Bomba',
                'lab' => 'Clarificador',
                'ip_address' => '10.0.2.21',
                'mac_address' => '00:11:22:33:44:04',
                'status' => true,
                'last_communication' => $now->copy()->subMinutes(3),
            ],
            [
                'name' => 'Estacion QA Laboratorio',
                'serial_number' => 'LAB-QA-001',
                'device_type' => 'Estacion de Monitoreo',
                'lab' => 'Laboratorio de Control',
                'ip_address' => '10.0.3.31',
                'mac_address' => '00:11:22:33:44:05',
                'status' => true,
                'last_communication' => $now->copy()->subMinutes(12),
            ],
            [
                'name' => 'Analizador Turbidez',
                'serial_number' => 'LAB-TUR-001',
                'device_type' => 'Analizador',
                'lab' => 'Clarificador',
                'ip_address' => '10.0.4.41',
                'mac_address' => '00:11:22:33:44:06',
                'status' => true,
                'last_communication' => $now->copy()->subMinutes(9),
            ],
        ];

        $savedDevices = [];

        foreach ($devices as $deviceData) {
            $device = Device::updateOrCreate(
                ['serial_number' => $deviceData['serial_number']],
                [
                    'name' => $deviceData['name'],
                    'device_type_id' => $deviceTypeMap[$deviceData['device_type']]->id,
                    'lab_id' => $labMap[$deviceData['lab']]->id,
                    'status' => $deviceData['status'],
                    'ip_address' => $deviceData['ip_address'],
                    'mac_address' => $deviceData['mac_address'],
                    'last_communication' => $deviceData['last_communication'],
                ]
            );

            $savedDevices[$device->name] = $device;
        }

        return $savedDevices;
    }

    /**
     * @param array<string,\App\Models\Device> $devices
     * @return array<string,\App\Models\Sensor>
     */
    private function seedSensors(array $devices): array
    {
        $sensorTypes = SensorType::all()->keyBy('name');

        $sensors = [
            ['name' => 'Temperatura Reactor A', 'device' => 'Nodo Multiparametro Reactor A', 'sensor_type' => 'Temperatura'],
            ['name' => 'pH Reactor A', 'device' => 'Nodo Multiparametro Reactor A', 'sensor_type' => 'pH'],
            ['name' => 'Oxigeno Disuelto Reactor A', 'device' => 'Nodo Multiparametro Reactor A', 'sensor_type' => 'Oxigeno Disuelto'],
            ['name' => 'pH Tanque Ajuste', 'device' => 'Controlador pH Tanque', 'sensor_type' => 'pH'],
            ['name' => 'Temperatura Reactor B', 'device' => 'Analizador Reactor B', 'sensor_type' => 'Temperatura'],
            ['name' => 'Conductividad Reactor B', 'device' => 'Analizador Reactor B', 'sensor_type' => 'Conductividad'],
            ['name' => 'Caudal Recirculacion', 'device' => 'Bomba Recirculacion', 'sensor_type' => 'Caudal'],
            ['name' => 'Turbidez Clarificador', 'device' => 'Analizador Turbidez', 'sensor_type' => 'Turbidez'],
            ['name' => 'ORP Reactor A', 'device' => 'Nodo Multiparametro Reactor A', 'sensor_type' => 'ORP'],
            ['name' => 'Oxigeno Disuelto QA', 'device' => 'Estacion QA Laboratorio', 'sensor_type' => 'Oxigeno Disuelto'],
        ];

        $savedSensors = [];

        foreach ($sensors as $sensorData) {
            $sensor = Sensor::updateOrCreate(
                ['name' => $sensorData['name']],
                [
                    'device_id' => $devices[$sensorData['device']]->id,
                    'sensor_type_id' => $sensorTypes[$sensorData['sensor_type']]->id,
                    'status' => true,
                ]
            );

            $savedSensors[$sensor->name] = $sensor;
        }

        return $savedSensors;
    }

    /**
     * @param array<string,\App\Models\Sensor> $sensors
     */
    private function seedSensorReadings(array $sensors): void
    {
        $readings = [
            'Temperatura Reactor A' => [22.4, 23.1, 30.5],
            'pH Reactor A' => [7.1, 7.3, 8.9],
            'Oxigeno Disuelto Reactor A' => [6.2, 5.8, 1.4],
            'pH Tanque Ajuste' => [6.8, 7.0, 9.2],
            'Temperatura Reactor B' => [20.3, 21.5, 27.2],
            'Conductividad Reactor B' => [1800, 2200, 16000],
            'Caudal Recirculacion' => [120, 140, 280],
            'Turbidez Clarificador' => [12, 18, 240],
            'ORP Reactor A' => [150, 220, -50],
            'Oxigeno Disuelto QA' => [7.8, 8.1, 2.1],
        ];

        $baseTime = Carbon::now()->subDay();
        $sensorIndex = 0;

        SensorReading::withoutEvents(function () use ($sensors, $readings, $baseTime, &$sensorIndex) {
            foreach ($readings as $sensorName => $values) {
                if (!isset($sensors[$sensorName])) {
                    continue;
                }

                foreach ($values as $offset => $value) {
                    SensorReading::create([
                        'sensor_id' => $sensors[$sensorName]->id,
                        'value' => $value,
                        'reading_time' => $baseTime->copy()->subMinutes(($sensorIndex * 10) + $offset * 3),
                    ]);
                }

                $sensorIndex++;
            }
        });
    }

    /**
     * @param array<string,\App\Models\Sensor> $sensors
     */
    private function seedAlerts(array $sensors): void
    {
        $sensorsWithAlerts = [
            'Temperatura Reactor A',
            'pH Reactor A',
            'Oxigeno Disuelto Reactor A',
            'Turbidez Clarificador',
            'Oxigeno Disuelto QA',
        ];

        foreach ($sensorsWithAlerts as $sensorName) {
            $sensor = $sensors[$sensorName] ?? null;

            if (!$sensor) {
                continue;
            }

            $latestReading = $sensor->readings()->orderByDesc('reading_time')->first();
            $rule = $sensor->alertRules()->first();

            if (!$latestReading || !$rule) {
                continue;
            }

            Alert::updateOrCreate(
                [
                    'sensor_reading_id' => $latestReading->id,
                    'alert_rule_id' => $rule->id,
                ],
                [
                    'resolved' => false,
                    'resolved_at' => null,
                ]
            );
        }
    }

    /**
     * @param array<string,\App\Models\Device> $devices
     */
    private function seedDeviceStatusLogs(array $devices): void
    {
        $now = Carbon::now();

        $statusSnapshots = [
            ['device' => 'Nodo Multiparametro Reactor A', 'status' => true, 'minutes_ago' => 30],
            ['device' => 'Nodo Multiparametro Reactor A', 'status' => false, 'minutes_ago' => 10],
            ['device' => 'Analizador Reactor B', 'status' => true, 'minutes_ago' => 25],
            ['device' => 'Analizador Reactor B', 'status' => true, 'minutes_ago' => 5],
            ['device' => 'Bomba Recirculacion', 'status' => true, 'minutes_ago' => 20],
            ['device' => 'Estacion QA Laboratorio', 'status' => true, 'minutes_ago' => 15],
            ['device' => 'Estacion QA Laboratorio', 'status' => false, 'minutes_ago' => 2],
            ['device' => 'Controlador pH Tanque', 'status' => true, 'minutes_ago' => 18],
        ];

        foreach ($statusSnapshots as $snapshot) {
            $device = $devices[$snapshot['device']] ?? null;

            if (!$device) {
                continue;
            }

            DeviceStatusLog::create([
                'device_id' => $device->id,
                'status' => $snapshot['status'],
                'changed_at' => $now->copy()->subMinutes($snapshot['minutes_ago']),
            ]);
        }
    }
}
