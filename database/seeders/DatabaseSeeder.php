<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use App\Models\Classroom;
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

        $this->seedCampuses();
        $devices = $this->seedDevices();
        $sensors = $this->seedSensors($devices);

        $this->call([
            AlertRuleSeeder::class,
        ]);

        $this->seedSensorReadings($sensors);
        $this->seedAlerts($sensors);
        $this->seedDeviceStatusLogs($devices);
    }

    private function seedCampuses(): void
    {
        $classrooms = [
            ['name' => 'Biblioteca Central', 'building' => 'A', 'floor' => 1, 'capacity' => 60],
            ['name' => 'Laboratorio de Física', 'building' => 'B', 'floor' => 2, 'capacity' => 30],
            ['name' => 'Laboratorio de Química', 'building' => 'B', 'floor' => 1, 'capacity' => 28],
            ['name' => 'Aula Magna', 'building' => 'C', 'floor' => 1, 'capacity' => 120],
            ['name' => 'Sala de Enfermería', 'building' => 'D', 'floor' => 1, 'capacity' => 20],
            ['name' => 'Cafetería Principal', 'building' => 'D', 'floor' => 1, 'capacity' => 80],
        ];

        foreach ($classrooms as $classroom) {
            Classroom::updateOrCreate(['name' => $classroom['name']], $classroom);
        }
    }

    /**
     * @return array<string,\App\Models\Device>
     */
    private function seedDevices(): array
    {
        $deviceTypeMap = DeviceType::all()->keyBy('name');
        $classroomMap = Classroom::all()->keyBy('name');
        $now = Carbon::now();

        $devices = [
            [
                'name' => 'Nodo Ambiental Biblioteca',
                'serial_number' => 'LIB-ENV-001',
                'device_type' => 'Calidad de Ambiente',
                'classroom' => 'Biblioteca Central',
                'ip_address' => '10.0.0.11',
                'mac_address' => '00:11:22:33:44:01',
                'status' => true,
                'last_communication' => $now->copy()->subMinutes(5),
            ],
            [
                'name' => 'Panel de Emergencia Biblioteca',
                'serial_number' => 'LIB-PAN-001',
                'device_type' => 'Pánico',
                'classroom' => 'Biblioteca Central',
                'ip_address' => '10.0.0.12',
                'mac_address' => '00:11:22:33:44:02',
                'status' => true,
                'last_communication' => $now->copy()->subMinutes(15),
            ],
            [
                'name' => 'Nodo Ambiental Física',
                'serial_number' => 'LABF-ENV-001',
                'device_type' => 'Calidad de Ambiente',
                'classroom' => 'Laboratorio de Física',
                'ip_address' => '10.0.1.11',
                'mac_address' => '00:11:22:33:44:03',
                'status' => true,
                'last_communication' => $now->copy()->subMinutes(7),
            ],
            [
                'name' => 'Monitor Sísmico Aula Magna',
                'serial_number' => 'AULA-SIS-001',
                'device_type' => 'Desastres',
                'classroom' => 'Aula Magna',
                'ip_address' => '10.0.2.21',
                'mac_address' => '00:11:22:33:44:04',
                'status' => true,
                'last_communication' => $now->copy()->subMinutes(3),
            ],
            [
                'name' => 'Detector de Humo Cafetería',
                'serial_number' => 'CAF-HUM-001',
                'device_type' => 'Desastres',
                'classroom' => 'Cafetería Principal',
                'ip_address' => '10.0.3.31',
                'mac_address' => '00:11:22:33:44:05',
                'status' => true,
                'last_communication' => $now->copy()->subMinutes(12),
            ],
            [
                'name' => 'Estación Enfermería',
                'serial_number' => 'ENF-ENV-001',
                'device_type' => 'Sensor Único',
                'classroom' => 'Sala de Enfermería',
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
                    'classroom_id' => $classroomMap[$deviceData['classroom']]->id,
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
            ['name' => 'Temperatura Biblioteca', 'device' => 'Nodo Ambiental Biblioteca', 'sensor_type' => 'Temperatura'],
            ['name' => 'Humedad Biblioteca', 'device' => 'Nodo Ambiental Biblioteca', 'sensor_type' => 'Humedad'],
            ['name' => 'CO2 Biblioteca', 'device' => 'Nodo Ambiental Biblioteca', 'sensor_type' => 'CO2'],
            ['name' => 'Monóxido Biblioteca', 'device' => 'Panel de Emergencia Biblioteca', 'sensor_type' => 'Monóxido de Carbono'],
            ['name' => 'Temperatura Laboratorio Física', 'device' => 'Nodo Ambiental Física', 'sensor_type' => 'Temperatura'],
            ['name' => 'Compuestos Orgánicos Laboratorio', 'device' => 'Nodo Ambiental Física', 'sensor_type' => 'Componentes Orgánicos'],
            ['name' => 'Vibración Aula Magna', 'device' => 'Monitor Sísmico Aula Magna', 'sensor_type' => 'Vibración'],
            ['name' => 'Humo Cafetería', 'device' => 'Detector de Humo Cafetería', 'sensor_type' => 'Humo'],
            ['name' => 'Oxígeno Enfermería', 'device' => 'Estación Enfermería', 'sensor_type' => 'Oxígeno'],
            ['name' => 'Presión Química', 'device' => 'Nodo Ambiental Física', 'sensor_type' => 'Presión Atmosférica'],
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
            'Temperatura Biblioteca' => [22.4, 23.1, 30.5],
            'Humedad Biblioteca' => [45.0, 48.2, 32.0],
            'CO2 Biblioteca' => [520, 560, 950],
            'Monóxido Biblioteca' => [8.0, 12.4, 40.0],
            'Temperatura Laboratorio Física' => [20.3, 21.5, 27.2],
            'Compuestos Orgánicos Laboratorio' => [120, 140, 260],
            'Vibración Aula Magna' => [0.2, 0.3, 1.8],
            'Humo Cafetería' => [0, 0.5, 7.2],
            'Oxígeno Enfermería' => [20.8, 20.9, 18.5],
            'Presión Química' => [1008, 1012, 995],
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
            'Temperatura Biblioteca',
            'Humedad Biblioteca',
            'CO2 Biblioteca',
            'Monóxido Biblioteca',
            'Humo Cafetería',
            'Oxígeno Enfermería',
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
            ['device' => 'Nodo Ambiental Biblioteca', 'status' => true, 'minutes_ago' => 30],
            ['device' => 'Nodo Ambiental Biblioteca', 'status' => false, 'minutes_ago' => 10],
            ['device' => 'Nodo Ambiental Física', 'status' => true, 'minutes_ago' => 25],
            ['device' => 'Nodo Ambiental Física', 'status' => true, 'minutes_ago' => 5],
            ['device' => 'Monitor Sísmico Aula Magna', 'status' => true, 'minutes_ago' => 20],
            ['device' => 'Detector de Humo Cafetería', 'status' => true, 'minutes_ago' => 15],
            ['device' => 'Detector de Humo Cafetería', 'status' => false, 'minutes_ago' => 2],
            ['device' => 'Estación Enfermería', 'status' => true, 'minutes_ago' => 18],
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
