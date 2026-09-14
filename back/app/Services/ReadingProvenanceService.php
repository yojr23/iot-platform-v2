<?php

namespace App\Services;

use App\Models\ReadingProjection;
use App\Models\SensorReading;

class ReadingProvenanceService
{
    /**
     * Create a reading projection for lineage tracking.
     */
    public function createProjection(
        SensorReading $reading,
        ?int $rawSensorEventId = null,
        string $sourceKey = 'unknown',
        string $normalizerVersion = 'v1'
    ): ReadingProjection {
        return ReadingProjection::create([
            'raw_sensor_event_id' => $rawSensorEventId,
            'sensor_reading_id' => $reading->id,
            'source_key' => $sourceKey,
            'normalizer_version' => $normalizerVersion,
        ]);
    }

    /**
     * Check if a reading projection already exists for a raw event and source.
     */
    public function projectionExists(?int $rawSensorEventId, string $sourceKey): bool
    {
        if (! $rawSensorEventId) {
            return false;
        }

        return ReadingProjection::where('raw_sensor_event_id', $rawSensorEventId)
            ->where('source_key', $sourceKey)
            ->exists();
    }

    /**
     * Get the reading projection for a sensor reading.
     */
    public function getProjectionForReading(SensorReading $reading): ?ReadingProjection
    {
        return ReadingProjection::where('sensor_reading_id', $reading->id)->first();
    }

    /**
     * Get all projections for a raw sensor event.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ReadingProjection>
     */
    public function getProjectionsForRawEvent(int $rawSensorEventId)
    {
        return ReadingProjection::where('raw_sensor_event_id', $rawSensorEventId)
            ->with('sensorReading')
            ->get();
    }

}
