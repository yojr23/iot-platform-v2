<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'serial_number', 'device_type_id', 'classroom_id', 
        'status', 'is_active', 'ip_address', 'mac_address', 'last_communication'
    ];

    protected $attributes = [
        'status' => true,
        'is_active' => true
    ];

    protected $casts = [
        'status' => 'boolean',
        'last_communication' => 'datetime',
    ];
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($device) {
            $device->api_key = bin2hex(random_bytes(32)); // Genera una clave de 64 caracteres
        });
    }
    public function deviceType()
    {
        return $this->belongsTo(DeviceType::class);
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function sensors()
    {
        return $this->hasMany(Sensor::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(DeviceStatusLog::class);
    }
}