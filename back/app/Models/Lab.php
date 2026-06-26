<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lab extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'area', 'process_line', 'description'];

    public function devices()
    {
        return $this->hasMany(Device::class);
    }
}
