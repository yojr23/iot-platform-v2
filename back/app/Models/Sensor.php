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
