<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    protected $fillable = [
        'reference_no', 'form_type_id', 'student_id', 'is_anonymous',
        'department_id', 'status', 'form_data', 'assigned_to',
        'current_step', 'submitted_at', 'sla_deadline', 'escalated_at',
        'resolved_at', 'internal_notes', 'output_document',
    ];

    protected $casts = [
        'form_data'      => 'array',
        'is_anonymous'   => 'boolean',
        'submitted_at'   => 'datetime',
        'sla_deadline'   => 'datetime',
        'escalated_at'   => 'datetime',
        'resolved_at'    => 'datetime',
    ];

    // Computed fields appended to every JSON response
    protected $appends = ['sla_breached'];

    // ----------------------------------------------------------
    // Status constants
    // ----------------------------------------------------------
    const STATUS_DRAFT            = 'draft';
    const STATUS_SUBMITTED        = 'submitted';
    const STATUS_ROUTED           = 'routed';
    const STATUS_IN_REVIEW        = 'in_review';
    const STATUS_ACTION_REQUIRED  = 'action_required';
    const STATUS_ESCALATED        = 'escalated';
    const STATUS_APPROVED         = 'approved';
    const STATUS_REJECTED         = 'rejected';
    const STATUS_RETURNED         = 'returned';
    const STATUS_COMPLETED        = 'completed';
    const STATUS_CANCELLED        = 'cancelled';

    public static function allStatuses(): array
    {
        return [
            self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_ROUTED,
            self::STATUS_IN_REVIEW, self::STATUS_ACTION_REQUIRED, self::STATUS_ESCALATED,
            self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_RETURNED,
            self::STATUS_COMPLETED, self::STATUS_CANCELLED,
        ];
    }

    // ----------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------
    public function isOpen(): bool
    {
        return !in_array($this->status, [
            self::STATUS_APPROVED, self::STATUS_REJECTED,
            self::STATUS_COMPLETED, self::STATUS_CANCELLED,
        ]);
    }

    public function isSlaBreached(): bool
    {
        return $this->sla_deadline && now()->isAfter($this->sla_deadline) && $this->isOpen();
    }

    // Accessor consumed by $appends — serialises as "sla_breached" in API responses
    public function getSlaBreachedAttribute(): bool
    {
        return $this->isSlaBreached();
    }

    // ----------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------
    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(SubmissionStatusHistory::class)->orderBy('changed_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SubmissionDocument::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SubmissionComment::class)->whereNull('parent_id')->orderBy('created_at');
    }

    public function approvalRecords(): HasMany
    {
        return $this->hasMany(ApprovalRecord::class)->orderBy('created_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
