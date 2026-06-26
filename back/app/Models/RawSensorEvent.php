<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawSensorEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic',
        'source',
        'node_id',
        'payload',
        'received_at',
        'status',
        'error',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
