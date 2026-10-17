<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\DeviceSensorMapping;
use App\Models\DeviceType;
use App\Models\Lab;
use App\Models\Sensor;
use App\Models\SensorType;
use Illuminate\Database\Seeder;

/**
 * Registra el nodo físico ESP32 de Oxígeno Disuelto del laboratorio para que
 * el pipeline de ingesta cruda (ingestion_service -> raw:consume) pueda
 * resolver node_id + sensor_key -> sensor_id.
 *
 * Contrato que debe calzar con el firmware (lab_esp32/firmware/do_sensor_node):
 *   - Device.serial_number == NODE_ID del firmware   ("lab_postgrado_nodo_01")
 *   - DeviceSensorMapping.source == "ingestion_service" (default del servicio Python)
 *   - DeviceSensorMapping.external_key == SENSOR_KEY en minúsculas ("dissolved_oxygen")
 *
 * Idempotente: usar firstOrCreate/updateOrCreate. Re-ejecutable sin duplicar.
 *
 *   php artisan db:seed --class=Database\\Seeders\\LabDoNodeSeeder
 */
class LabDoNodeSeeder extends Seeder
{
    // Debe coincidir con config.h del firmware.
    private const NODE_ID     = 'lab_postgrado_nodo_01';
    private const SENSOR_KEY  = 'dissolved_oxygen';
    private const SOURCE      = 'ingestion_service';

    public function run(): void
    {
        $lab = Lab::updateOrCreate(
            ['name' => 'Laboratorio Posgrado Quimica'],
            [
                'area'          => 'Posgrado',
                'process_line'  => 'Monitoreo de calidad de agua',
                'description'   => 'Nodo multiparámetro UNAB - Posgrado Química',
            ],
        );

        // "Estacion de Monitoreo" lo crea DeviceTypeSeeder; si no existe, lo creamos.
        $deviceType = DeviceType::updateOrCreate(
            ['name' => 'Estacion de Monitoreo'],
            ['description' => 'Nodos de monitoreo multiparametro'],
        );

        // "Oxigeno Disuelto" lo crea SensorTypeSeeder; si no existe, lo creamos.
        $sensorType = SensorType::updateOrCreate(
            ['name' => 'Oxigeno Disuelto'],
            ['unit' => 'mg/L', 'min_range' => 0, 'max_range' => 20],
        );

        $device = Device::firstOrCreate(
            ['serial_number' => self::NODE_ID],
            [
                'name'           => 'Nodo DO - Posgrado',
                'device_type_id' => $deviceType->id,
                'lab_id'         => $lab->id,
                'status'         => true,
                'is_active'      => true,
            ],
        );

        $sensor = Sensor::firstOrCreate(
            ['device_id' => $device->id, 'sensor_type_id' => $sensorType->id],
            [
                'name'                      => 'Oxígeno Disuelto',
                'status'                    => true,
                'public_monitoring_enabled' => true,
            ],
        );

        DeviceSensorMapping::updateOrCreate(
            [
                'device_id'    => $device->id,
                'source'       => self::SOURCE,
                'external_key' => self::SENSOR_KEY,
            ],
            [
                'sensor_id'  => $sensor->id,
                'is_active'  => true,
                'valid_from' => null,   // válido desde siempre
                'valid_until' => null,  // sin expiración
            ],
        );

        $this->command?->info(sprintf(
            'LabDoNodeSeeder OK: device#%d serial=%s  sensor#%d  mapping %s/%s',
            $device->id, self::NODE_ID, $sensor->id, self::SOURCE, self::SENSOR_KEY,
        ));
    }
}
