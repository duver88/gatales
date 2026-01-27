<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AiSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'label',
        'description',
        'options',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    /**
     * Get a setting value by key
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember("ai_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value by key
     */
    public static function setValue(string $key, mixed $value): bool
    {
        $setting = self::where('key', $key)->first();

        if ($setting) {
            $setting->update(['value' => $value]);
            Cache::forget("ai_setting_{$key}");
            Cache::forget('ai_settings_all');
            return true;
        }

        return false;
    }

    /**
     * Get all settings as key-value pairs
     */
    public static function getAllValues(): array
    {
        return Cache::remember('ai_settings_all', 3600, function () {
            return self::pluck('value', 'key')->toArray();
        });
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        $settings = self::all();
        foreach ($settings as $setting) {
            Cache::forget("ai_setting_{$setting->key}");
        }
        Cache::forget('ai_settings_all');
    }
}
