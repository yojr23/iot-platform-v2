<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    // valid_from / valid_until are handled by dedicated Attributes below rather than the
    // 'datetime' cast: the default cast stores a Z/offset string as a naive wall-clock
    // (dropping the offset), which disagrees with RawReadingNormalizer::lookupTime() that
    // converts the reading timestamp into app time. That mismatch made temporal mapping
    // lookups miss (skipped instead of created). The Attributes normalize the boundaries the
    // same way lookupTime does, so storage and lookup are always in the same timezone.
    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected function validFrom(): Attribute
    {
        return $this->validityBoundaryAttribute();
    }

    protected function validUntil(): Attribute
    {
        return $this->validityBoundaryAttribute();
    }

    /**
     * Shared accessor/mutator for temporal validity boundaries.
     *
     * set: parse honoring any embedded offset (e.g. trailing Z = UTC); a plain datetime with
     *      no offset is interpreted in the app timezone. The value is stored in app time so it
     *      is directly comparable to the normalizer's lookup time.
     * get: hydrate the stored wall-clock back into an app-timezone Carbon instance.
     */
    private function validityBoundaryAttribute(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?Carbon => $value === null
                ? null
                : Carbon::createFromFormat('Y-m-d H:i:s', $value, config('app.timezone')),
            set: function ($value): ?string {
                if ($value === null) {
                    return null;
                }

                $tz = config('app.timezone');
                $carbon = $value instanceof DateTimeInterface
                    ? Carbon::instance($value)
                    : Carbon::parse($value, $tz);

                return $carbon->setTimezone($tz)->format('Y-m-d H:i:s');
            },
        );
    }

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
