<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function fields()
    {
        return $this->hasMany(FormField::class)->orderBy('sort_order');
    }

    public function workflow()
    {
        return $this->hasOne(Workflow::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * Effective SLA in hours: form-specific if set, otherwise fall back to department default.
     */
    public function effectiveSlaHours(): int
    {
        return $this->sla_hours ?? $this->department->sla_hours;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
