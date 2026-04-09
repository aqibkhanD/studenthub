<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormType extends Model
{
    protected $fillable = [
        'slug', 'name', 'category', 'department_id',
        'sla_hours', 'requires_docs', 'allow_anonymous',
        'auto_generate', 'instructions', 'fields',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'fields'          => 'array',
        'requires_docs'   => 'boolean',
        'allow_anonymous' => 'boolean',
        'auto_generate'   => 'boolean',
        'is_active'       => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
