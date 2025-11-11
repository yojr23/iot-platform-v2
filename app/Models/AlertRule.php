<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'sensor_type_id',
        'device_id',
        'sensor_id',
        'min_value',
        'max_value',
        'severity',
        'message',
        'name',
    ];

    public function sensorType()
    {
        return $this->belongsTo(SensorType::class);
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function sensor()
    {
        return $this->belongsTo(Sensor::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }
}
