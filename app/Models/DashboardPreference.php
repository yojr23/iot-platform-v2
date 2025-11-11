<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DashboardPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'layout',
    ];

    protected $casts = [
        'layout' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

