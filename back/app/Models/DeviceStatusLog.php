<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceStatusLog extends Model
{
    use HasFactory;

    protected $fillable = ['device_id', 'status', 'changed_at'];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }
}
