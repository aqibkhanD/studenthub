<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FormType extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'department_id', 'description',
        'instructions', 'requires_documents', 'allow_anonymous',
        'auto_generate_doc', 'doc_template_path', 'sla_hours',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'requires_documents' => 'boolean',
        'allow_anonymous'    => 'boolean',
        'auto_generate_doc'  => 'boolean',
        'is_active'          => 'boolean',
    ];

    public function effectiveSlaHours(): int
    {
        // Use form type override → department default → global fallback (48h)
        return $this->sla_hours ?? $this->department?->sla_hours ?? 48;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    public function workflow(): HasOne
    {
        return $this->hasOne(Workflow::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
