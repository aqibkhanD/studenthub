<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRecord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'submission_id', 'workflow_step_id', 'approver_id',
        'action', 'comment', 'acted_at', 'created_at',
    ];

    protected $casts = [
        'acted_at'   => 'datetime',
        'created_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function workflowStep()
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function isPending():  bool { return $this->action === 'pending'; }
    public function isApproved(): bool { return $this->action === 'approved'; }
    public function isRejected(): bool { return $this->action === 'rejected'; }
}
