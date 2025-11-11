<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'building', 'floor', 'capacity'];

    public function devices()
    {
        return $this->hasMany(Device::class);
    }
}
