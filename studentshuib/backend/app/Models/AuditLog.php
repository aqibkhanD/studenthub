<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'old_values'  => 'array',
        'new_values'  => 'array',
        'created_at'  => 'datetime',
    ];

    // Frontend-friendly aliases
    protected $appends = ['entity_type', 'entity_id', 'changes', 'description'];

    /** Frontend uses `entity_type` — strips namespace from `auditable_type`. */
    public function getEntityTypeAttribute(): ?string
    {
        if (!$this->auditable_type) return null;
        return class_basename($this->auditable_type);
    }

    /** Frontend uses `entity_id` — alias for `auditable_id`. */
    public function getEntityIdAttribute(): ?int
    {
        return $this->auditable_id;
    }

    /** Frontend uses `changes` — merged diff of old vs new values. */
    public function getChangesAttribute(): ?array
    {
        if (empty($this->new_values) && empty($this->old_values)) return null;
        return array_filter([
            'before' => $this->old_values,
            'after'  => $this->new_values,
        ]);
    }

    /** Frontend uses `description` — human-readable action label. */
    public function getDescriptionAttribute(): string
    {
        return str_replace(['.', '_'], ' ', $this->action);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
