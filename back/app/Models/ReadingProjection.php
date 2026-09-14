<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingProjection extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_sensor_event_id',
        'sensor_reading_id',
        'source_key',
        'normalizer_version',
    ];

    public function rawSensorEvent(): BelongsTo
    {
        return $this->belongsTo(RawSensorEvent::class);
    }

    public function sensorReading(): BelongsTo
    {
        return $this->belongsTo(SensorReading::class);
    }
}
