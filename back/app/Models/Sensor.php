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

    public function alertRules()
    {
        return $this->hasMany(AlertRule::class);
    }
}
