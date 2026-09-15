<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceSensorMapping;
use App\Models\Sensor;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class SensorMappingService
{
    /**
     * Find a sensor mapping by device, source, and external key at a given time.
     */
    public function findSensorByExternalKey(
        Device $device,
        string $source,
        string $externalKey,
        ?DateTimeInterface $at = null,
    ): ?Sensor {
        $at ??= now();

        $mapping = DeviceSensorMapping::where('device_id', $device->id)
            ->where('source', $source)
            ->where('external_key', $externalKey)
            ->where('is_active', true)
            ->where(function ($q) use ($at) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>', $at);
            })
            ->first();

        return $mapping?->sensor;
    }

    /**
     * Create a new temporal mapping interval.
     *
     * Deactivates any existing active mapping for the same (device, source, external_key)
     * by closing its validity window, then inserts a new open-ended interval.
     * Rejects overlapping intervals within the same transaction.
     */
    public function mapSensor(
        Device $device,
        Sensor $sensor,
        string $source,
        string $externalKey,
        ?array $metadata = null,
    ): DeviceSensorMapping {
        return DB::transaction(function () use ($device, $sensor, $source, $externalKey, $metadata) {
            $now = now();

            // Close any currently active mapping for this key
            DeviceSensorMapping::where('device_id', $device->id)
                ->where('source', $source)
                ->where('external_key', $externalKey)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'valid_until' => $now,
                ]);

            return DeviceSensorMapping::create([
                'device_id' => $device->id,
                'sensor_id' => $sensor->id,
                'source' => $source,
                'external_key' => $externalKey,
                'is_active' => true,
                'valid_from' => $now,
                'valid_until' => null,
                'metadata' => $metadata,
            ]);
        });
    }

    public function deactivateMapping(DeviceSensorMapping $mapping): bool
    {
        return $mapping->update([
            'is_active' => false,
            'valid_until' => now(),
        ]);
    }

    public function getActiveMappingsForDevice(Device $device)
    {
        return DeviceSensorMapping::where('device_id', $device->id)
            ->where('is_active', true)
            ->with('sensor')
            ->get();
    }

    public function getActiveMappingsForSensor(Sensor $sensor)
    {
        return DeviceSensorMapping::where('sensor_id', $sensor->id)
            ->where('is_active', true)
            ->with('device')
            ->get();
    }
}
