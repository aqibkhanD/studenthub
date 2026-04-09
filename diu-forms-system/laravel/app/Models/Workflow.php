<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    protected $fillable = ['name', 'form_type_id', 'type', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function formType()
    {
        return $this->belongsTo(FormType::class);
    }

    public function steps()
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('step_number');
    }

    public function isSingle():     bool { return $this->type === 'single'; }
    public function isSequential(): bool { return $this->type === 'sequential'; }
    public function isParallel():   bool { return $this->type === 'parallel'; }
}
