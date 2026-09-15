<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'serial_number', 'device_type_id', 'lab_id',
        'status', 'is_active', 'ip_address', 'mac_address', 'last_communication',
        'api_key_hash', 'api_key_prefix', 'api_key_last_rotated_at'
    ];

    // SEC-BOLA-002/SEC-CONFIG-001: defense-in-depth so api_key_hash never leaks through an
    // accidental toArray()/toJson().
    protected $hidden = ['api_key_hash'];

    protected $attributes = [
        'status' => true,
        'is_active' => true
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_active' => 'boolean',
        'last_communication' => 'datetime',
        'api_key_last_rotated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($device) {
            $plaintextKey = bin2hex(random_bytes(32));
            $device->api_key_hash = hash('sha256', $plaintextKey);
            $device->api_key_prefix = substr($plaintextKey, 0, 8);
            $device->api_key_last_rotated_at = now();
            $device->_plaintextKey = $plaintextKey;
        });
    }

    /**
     * Return the plaintext key generated during the most recent create/rotate operation.
     * Available only on the model instance immediately after creation or rotation.
     */
    public function getPlaintextKey(): ?string
    {
        return $this->_plaintextKey ?? null;
    }

    public function deviceType()
    {
        return $this->belongsTo(DeviceType::class);
    }

    public function lab()
    {
        return $this->belongsTo(Lab::class);
    }

    public function sensors()
    {
        return $this->hasMany(Sensor::class);
    }

    public function sensorMappings()
    {
        return $this->hasMany(DeviceSensorMapping::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(DeviceStatusLog::class);
    }

    /**
     * Authenticate a device using the plaintext API key.
     * Returns true if the hash of the provided key matches the stored hash.
     * Hash-only: no plaintext fallback. Legacy plaintext migration happens
     * via an explicit backfill migration, not at authentication time.
     */
    public function authenticate(string $plaintextKey): bool
    {
        if (! $this->api_key_hash) {
            return false;
        }

        return hash_equals($this->api_key_hash, hash('sha256', $plaintextKey));
    }

    /**
     * Rotate the device API key.
     * Returns the new plaintext key (shown once, never stored).
     * Stores only hash + prefix + rotation timestamp.
     */
    public function rotateApiKey(): string
    {
        $newPlaintextKey = bin2hex(random_bytes(32));

        $this->update([
            'api_key_hash' => hash('sha256', $newPlaintextKey),
            'api_key_prefix' => substr($newPlaintextKey, 0, 8),
            'api_key_last_rotated_at' => now(),
        ]);

        return $newPlaintextKey;
    }
}
