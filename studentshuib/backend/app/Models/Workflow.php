<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    protected $fillable = ['name', 'form_type_id', 'type', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_number');
    }
}
