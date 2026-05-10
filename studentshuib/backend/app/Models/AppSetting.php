<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Generic key/value system settings store.
 *
 * Usage:
 *   AppSetting::get('semester.label', 'Current Semester');
 *   AppSetting::set('semester.label', 'Fall 2026', $userId);
 */
class AppSetting extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $primaryKey = 'key';
    protected $keyType    = 'string';

    protected $fillable = ['key', 'value', 'updated_by', 'updated_at'];
    protected $casts    = [
        'value'      => 'array',  // jsonb decode
        'updated_at' => 'datetime',
    ];

    /**
     * Read a setting value, falling back to $default if missing.
     * The jsonb cast unwraps scalars/arrays automatically.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::find($key);
        return $row ? $row->value : $default;
    }

    /**
     * Write a setting value. $userId is recorded for the audit trail.
     */
    public static function set(string $key, mixed $value, ?int $userId = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $userId, 'updated_at' => now()]
        );
    }

    /**
     * Convenience helper for the dashboard: returns the current semester
     * window as a [label, start, end] tuple with sensible defaults.
     */
    public static function semester(): array
    {
        return [
            'label' => static::get('semester.label', 'Current Semester'),
            'start' => static::get('semester.start_date', now()->subMonths(6)->format('Y-m-d')),
            'end'   => static::get('semester.end_date',   now()->format('Y-m-d')),
        ];
    }
}
