<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Get a setting value by key
     */
    public static function get(string $key, $default = null)
    {
        try {
            return Cache::remember("system_setting_{$key}", 3600, function () use ($key, $default) {
                $setting = static::where('key', $key)->first();
                return $setting ? static::castValue($setting->value, $setting->type) : $default;
            });
        } catch (Throwable $e) {
            Log::error('SystemSetting: cache read failed, falling through to DB', [
                'key' => $key,
                'exception' => $e->getMessage(),
            ]);

            $setting = static::where('key', $key)->first();
            return $setting ? static::castValue($setting->value, $setting->type) : $default;
        }
    }

    /**
     * Set a setting value
     */
    public static function set(string $key, $value, string $type = 'string', string $group = 'general', string $description = null, bool $isPublic = false): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'type' => $type,
                'group' => $group,
                'description' => $description,
                'is_public' => $isPublic,
            ]
        );

        // Clear cache
        try {
            Cache::forget("system_setting_{$key}");
            Cache::forget("system_settings_group_{$group}");
            Cache::forget('system_settings_groups');
        } catch (Throwable $e) {
            Log::error('SystemSetting: cache clear failed', [
                'key' => $key,
                'group' => $group,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup(string $group): array
    {
        try {
            return Cache::remember("system_settings_group_{$group}", 3600, function () use ($group) {
                return static::where('group', $group)
                    ->get()
                    ->mapWithKeys(function ($setting) {
                        return [$setting->key => static::castValue($setting->value, $setting->type)];
                    })
                    ->toArray();
            });
        } catch (Throwable $e) {
            Log::error('SystemSetting: cache read failed for group, falling through to DB', [
                'group' => $group,
                'exception' => $e->getMessage(),
            ]);

            return static::where('group', $group)
                ->get()
                ->mapWithKeys(function ($setting) {
                    return [$setting->key => static::castValue($setting->value, $setting->type)];
                })
                ->toArray();
        }
    }

    /**
     * Cast value based on type
     */
    protected static function castValue($value, string $type)
    {
        switch ($type) {
            case 'integer':
            case 'int':
                return (int) $value;
            case 'boolean':
            case 'bool':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode($value, true);
            case 'float':
            case 'double':
                return (float) $value;
            default:
                return $value;
        }
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        try {
            $settings = static::all();
            foreach ($settings as $setting) {
                Cache::forget("system_setting_{$setting->key}");
            }
            Cache::forget('system_settings_groups');
        } catch (Throwable $e) {
            Log::error('SystemSetting: clearCache failed', [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
