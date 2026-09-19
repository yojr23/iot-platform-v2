<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawSensorEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic',
        'source',
        'source_event_id',
        'node_id',
        'payload',
        'received_at',
        'status',
        'error',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Normalize `received_at` to the APP_TIMEZONE wall clock on write, the same contract
     * SensorReadingService::normalizeReadingTime() applies to reading_time. Without this an
     * ingestion `received_at` carrying a `Z`/UTC offset (e.g. "2026-09-19T17:43:53Z") is stored as
     * the raw UTC digits ("17:43:53") because Eloquent formats the Carbon in its own timezone, then
     * the `datetime` cast reads those digits back as APP_TIMEZONE — a fixed offset error that made
     * every MQTT-sourced reading_time land hours in the future and be rejected by the browser's
     * clock-drift guard (front/src/stores/sensorReadings.js). Converting to APP_TIMEZONE on write
     * makes the stored digits mean the instant they represent, so the reading_time derived from
     * received_at and the broadcast UTC serialization round-trip correctly.
     */
    protected function receivedAt(): Attribute
    {
        return Attribute::make(
            set: function ($value) {
                if ($value === null || $value === '') {
                    return null;
                }

                $appTimezone = config('app.timezone');

                if ($value instanceof DateTimeInterface) {
                    return Carbon::instance($value)->setTimezone($appTimezone)->format('Y-m-d H:i:s');
                }

                return Carbon::parse($value, $appTimezone)->setTimezone($appTimezone)->format('Y-m-d H:i:s');
            },
        );
    }
}
