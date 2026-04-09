<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'submission_id', 'changed_by', 'from_status', 'to_status',
        'comment', 'is_visible_to_student', 'step_number', 'changed_at',
    ];

    protected $casts = [
        'is_visible_to_student' => 'boolean',
        'changed_at'            => 'datetime',
    ];

    // Aliases for frontend compatibility
    protected $appends = ['new_status', 'created_at'];

    /** Frontend uses `new_status` — alias for `to_status`. */
    public function getNewStatusAttribute(): ?string
    {
        return $this->to_status;
    }

    /** Frontend uses `created_at` — alias for `changed_at`. */
    public function getCreatedAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->changed_at;
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
