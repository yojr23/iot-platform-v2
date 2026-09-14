<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceSensorMapping;
use App\Models\Sensor;

class SensorMappingService
{
    /**
     * Find a sensor by device, source, and external key.
     */
    public function findSensorByExternalKey(Device $device, string $source, string $externalKey): ?Sensor
    {
        $mapping = DeviceSensorMapping::findByExternalKey($device->id, $source, $externalKey);

        return $mapping?->sensor;
    }

    /**
     * Create or update a sensor mapping.
     */
    public function mapSensor(
        Device $device,
        Sensor $sensor,
        string $source,
        string $externalKey,
        ?array $metadata = null
    ): DeviceSensorMapping {
        return DeviceSensorMapping::updateOrCreate(
            [
                'device_id' => $device->id,
                'source' => $source,
                'external_key' => $externalKey,
            ],
            [
                'sensor_id' => $sensor->id,
                'metadata' => $metadata,
                'is_active' => true,
            ]
        );
    }

    /**
     * Deactivate a sensor mapping.
     */
    public function deactivateMapping(DeviceSensorMapping $mapping): bool
    {
        return $mapping->update(['is_active' => false]);
    }

    /**
     * Get all active mappings for a device.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DeviceSensorMapping>
     */
    public function getActiveMappingsForDevice(Device $device)
    {
        return DeviceSensorMapping::where('device_id', $device->id)
            ->where('is_active', true)
            ->with('sensor')
            ->get();
    }

    /**
     * Get all active mappings for a sensor.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DeviceSensorMapping>
     */
    public function getActiveMappingsForSensor(Sensor $sensor)
    {
        return DeviceSensorMapping::where('sensor_id', $sensor->id)
            ->where('is_active', true)
            ->with('device')
            ->get();
    }
}
