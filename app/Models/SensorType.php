<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SensorType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'unit', 'min_range', 'max_range'];

    public function sensors()
    {
        return $this->hasMany(Sensor::class);
    }

    public function alertRules()
    {
        return $this->hasMany(AlertRule::class);
    }
}
