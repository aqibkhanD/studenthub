<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStep extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workflow_id', 'step_number', 'step_name', 'department_id',
        'assigned_role', 'action_required', 'sla_hours', 'is_optional',
    ];

    protected $casts = ['is_optional' => 'boolean'];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
