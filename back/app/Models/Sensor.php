<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sensor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'device_id',
        'sensor_type_id',
        'status',
        'public_monitoring_enabled',
    ];

    protected $casts = [
        'status' => 'boolean',
        'public_monitoring_enabled' => 'boolean',
    ];

    // Relación con el dispositivo
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    // Relación con el tipo de sensor
    public function sensorType()
    {
        return $this->belongsTo(SensorType::class);
    }

    // Relación con las lecturas
    public function readings()
    {
        return $this->hasMany(SensorReading::class);
    }

    // D4 (front_rebuild_plan/MAIN_PARITY_GAPS_PLAN.md): reuses the same `readings` hasMany above
    // via Eloquent's built-in "has one of many" relation instead of inventing a helper/service.
    // Needed because eager-loading `readings` with a raw `->limit(1)` constraint does NOT scope
    // per sensor — it applies a single combined LIMIT across all matched sensor_ids, so only one
    // sensor in a multi-sensor device would get a row. `latestOfMany()` correctly resolves one
    // true-latest row per parent and is eager-load/N+1 safe.
    public function latestReading()
    {
        return $this->hasOne(SensorReading::class)->latestOfMany('reading_time');
    }

    public function alertRules()
    {
        return $this->hasMany(AlertRule::class);
    }
}
