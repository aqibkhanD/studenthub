<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionStatusHistory extends Model
{
    public $timestamps = false;   // uses changed_at only

    protected $fillable = [
        'submission_id', 'changed_by', 'from_status', 'to_status',
        'comment', 'is_visible_to_student', 'step_number', 'changed_at',
    ];

    protected $casts = [
        'is_visible_to_student' => 'boolean',
        'changed_at'            => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
