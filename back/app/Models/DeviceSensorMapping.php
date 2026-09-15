<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSensorMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'sensor_id',
        'source',
        'external_key',
        'is_active',
        'valid_from',
        'valid_until',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'metadata' => 'array',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * Find a sensor mapping by device, source, and external key.
     */
    public static function findByExternalKey(
        int $deviceId,
        string $source,
        string $externalKey,
        ?DateTimeInterface $at = null,
    ): ?self
    {
        $at ??= now();

        return static::where('device_id', $deviceId)
            ->where('source', $source)
            ->where('external_key', $externalKey)
            ->where(function ($query) use ($at) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $at);
            })
            ->where(function ($query) use ($at) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', $at);
            })
            ->orderByDesc('valid_from')
            ->orderByDesc('id')
            ->first();
    }
}
