<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key-value settings table.
 *
 * Columns: id, key (unique), value (text, JSON-encoded for complex values),
 *          group (string, for logical grouping), created_at, updated_at
 *
 * Usage:
 *   Setting::get('university_name', 'Daffodil International University')
 *   Setting::set('university_name', 'New Name')
 *   Setting::setMany(['key' => 'value', ...])
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    /** Cache TTL in seconds (10 minutes). */
    private const CACHE_TTL = 600;

    // ------------------------------------------------------------------
    // Static helpers
    // ------------------------------------------------------------------

    /**
     * Retrieve a setting value by key, with an optional default.
     * Results are cached to avoid per-request DB hits.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::allCached();

        if (!array_key_exists($key, $all)) {
            return $default;
        }

        $raw = $all[$key];

        // Attempt JSON decode for complex values stored as JSON strings
        $decoded = json_decode($raw, true);

        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $raw;
    }

    /**
     * Retrieve all settings in a given group.
     */
    public static function getGroup(string $group): array
    {
        return Cache::remember("settings_group_{$group}", self::CACHE_TTL, function () use ($group) {
            return self::where('group', $group)
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /**
     * Persist a single setting. Clears relevant caches.
     */
    public static function set(string $key, mixed $value): void
    {
        $encoded = is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : (string) $value;

        self::updateOrCreate(['key' => $key], ['value' => $encoded]);

        self::bustCache();
    }

    /**
     * Persist multiple settings at once within a single transaction.
     */
    public static function setMany(array $pairs, string $group = 'general'): void
    {
        \DB::transaction(function () use ($pairs, $group) {
            foreach ($pairs as $key => $value) {
                $encoded = is_array($value) || is_object($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE)
                    : (string) $value;

                self::updateOrCreate(
                    ['key' => $key],
                    ['value' => $encoded, 'group' => $group]
                );
            }
        });

        self::bustCache();
    }

    // ------------------------------------------------------------------
    // Cache management
    // ------------------------------------------------------------------

    /** Return all settings as a flat key => raw-value map (cached). */
    private static function allCached(): array
    {
        return Cache::remember('settings_all', self::CACHE_TTL, function () {
            return self::pluck('value', 'key')->toArray();
        });
    }

    /** Invalidate all settings-related cache entries. */
    public static function bustCache(): void
    {
        Cache::forget('settings_all');
        // Also bust any group caches — we don't know which group changed,
        // so we rely on the TTL for group caches; for immediate consistency
        // callers can pass the group explicitly.
        foreach (['general', 'branding', 'portal', 'features', 'sms'] as $g) {
            Cache::forget("settings_group_{$g}");
        }
    }
}
