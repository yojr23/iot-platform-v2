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

    // SEC-BOLA-002/SEC-CONFIG-001: defense-in-depth so api_key never leaks through an
    // accidental toArray()/toJson() outside DeviceResource's explicit one-time reveal.
    protected $hidden = ['api_key', 'api_key_hash'];

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
            $device->api_key = $plaintextKey;
            $device->api_key_hash = hash('sha256', $plaintextKey);
            $device->api_key_prefix = substr($plaintextKey, 0, 8);
            $device->api_key_last_rotated_at = now();
        });
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
     * Returns true if the key matches the stored hash.
     */
    public function authenticate(string $plaintextKey): bool
    {
        // First try to authenticate using the stored hash
        if ($this->api_key_hash && hash('sha256', $plaintextKey) === $this->api_key_hash) {
            return true;
        }

        // Legacy fallback: compare against plaintext key (for migration period)
        if ($this->api_key && hash_equals($this->api_key, $plaintextKey)) {
            // Auto-migrate to hashed key
            $this->update([
                'api_key_hash' => hash('sha256', $plaintextKey),
                'api_key_prefix' => substr($plaintextKey, 0, 8),
                'api_key_last_rotated_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Rotate the device API key.
     * Returns the new plaintext key (only shown once).
     */
    public function rotateApiKey(): string
    {
        $newPlaintextKey = bin2hex(random_bytes(32));

        $this->update([
            'api_key' => $newPlaintextKey,
            'api_key_hash' => hash('sha256', $newPlaintextKey),
            'api_key_prefix' => substr($newPlaintextKey, 0, 8),
            'api_key_last_rotated_at' => now(),
        ]);

        return $newPlaintextKey;
    }
}
