<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceSensorMapping;
use App\Models\Sensor;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        $externalKey = $this->canonicalExternalKey($externalKey);

        $mapping = DeviceSensorMapping::query()
            ->with('sensor')
            ->where('device_id', $device->id)
            ->where('source', $source)
            ->where('external_key', $externalKey)
            ->where(function ($q) use ($at) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>', $at);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();

        return $mapping?->sensor;
    }

    /**
     * Resolve a batch of external keys at one event instant.
     *
     * `is_active` describes the current operational interval only. Historical lookups must use
     * the half-open validity window, otherwise a handover makes old receipts unresolvable.
     *
     * @param  list<string>  $externalKeys
     * @return Collection<string, Sensor>
     */
    public function findSensorsByExternalKeys(
        Device $device,
        string $source,
        array $externalKeys,
        DateTimeInterface $at,
    ): Collection {
        if ($externalKeys === []) {
            return collect();
        }

        $externalKeys = array_values(array_unique(array_map(
            fn (string $externalKey): string => $this->canonicalExternalKey($externalKey),
            $externalKeys,
        )));

        return DeviceSensorMapping::query()
            ->with('sensor')
            ->where('device_id', $device->id)
            ->where('source', $source)
            ->whereIn('external_key', $externalKeys)
            ->where(function ($q) use ($at) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>', $at);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->get()
            ->unique('external_key')
            ->mapWithKeys(fn (DeviceSensorMapping $mapping): array => [$mapping->external_key => $mapping->sensor]);
    }

    /**
     * Create a new temporal mapping interval.
     *
     * Deactivates any existing active mapping for the same (device, source, external_key)
     * by closing its validity window, then inserts a new open-ended interval.
     * The current interval is closed before its replacement is inserted.
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
            $externalKey = $this->canonicalExternalKey($externalKey);

            // P1: lock existing mappings for this identity to serialize concurrent writers.
            // Without this, two concurrent mapSensor() calls can both read "no active mapping"
            // and both insert, producing two open intervals for the same key.
            DeviceSensorMapping::where('device_id', $device->id)
                ->where('source', $source)
                ->where('external_key', $externalKey)
                ->lockForUpdate()
                ->get();

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

    private function canonicalExternalKey(string $externalKey): string
    {
        return Str::lower(trim($externalKey));
    }
}
