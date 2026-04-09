<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRecord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'submission_id', 'workflow_step_id', 'approver_id',
        'action', 'comment', 'acted_at',
    ];

    protected $casts = ['acted_at' => 'datetime', 'created_at' => 'datetime'];

    protected $appends = ['step_order'];

    /**
     * Expose `step_order` from the related WorkflowStep.
     * Frontend uses `record.step_order` for display.
     */
    public function getStepOrderAttribute(): ?int
    {
        return $this->workflowStep?->step_number;
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
