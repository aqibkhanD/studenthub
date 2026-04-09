<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Services\SubmissionStateMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Submission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference_no', 'form_type_id', 'student_id', 'is_anonymous',
        'department_id', 'status', 'form_data', 'assigned_to',
        'current_step', 'submitted_at', 'sla_deadline', 'escalated_at',
        'resolved_at', 'internal_notes', 'output_document',
    ];

    protected $casts = [
        'form_data'    => 'array',
        'is_anonymous' => 'boolean',
        'status'       => SubmissionStatus::class,
        'submitted_at' => 'datetime',
        'sla_deadline' => 'datetime',
        'escalated_at' => 'datetime',
        'resolved_at'  => 'datetime',
    ];

    // ── Relationships ────────────────────────────────
    public function formType()
    {
        return $this->belongsTo(FormType::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function statusHistory()
    {
        return $this->hasMany(SubmissionStatusHistory::class)->orderBy('changed_at');
    }

    public function documents()
    {
        return $this->hasMany(SubmissionDocument::class);
    }

    public function studentDocuments()
    {
        return $this->documents()->where('document_type', 'student_upload');
    }

    public function comments()
    {
        return $this->hasMany(SubmissionComment::class)->orderBy('created_at');
    }

    public function publicComments()
    {
        return $this->comments()->where('is_internal', false);
    }

    public function approvalRecords()
    {
        return $this->hasMany(ApprovalRecord::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // ── SLA helpers ──────────────────────────────────
    public function isSlaBreached(): bool
    {
        return $this->sla_deadline && now()->isAfter($this->sla_deadline)
            && !$this->status->isTerminal();
    }

    public function slaRemainingHours(): float
    {
        if (!$this->sla_deadline) return 0;
        return max(0, now()->diffInHours($this->sla_deadline, false));
    }

    public function slaStatus(): string
    {
        $hours = $this->slaRemainingHours();
        if ($this->isSlaBreached())  return 'breach';
        if ($hours <= 4)             return 'warn';
        return 'ok';
    }

    // ── State machine proxy ──────────────────────────
    public function canTransitionTo(SubmissionStatus $next): bool
    {
        return $this->status->canTransitionTo($next);
    }

    /**
     * Transition to a new status.
     * All status changes MUST go through this method — never set status directly.
     *
     * @throws \Exception if transition is not allowed
     */
    public function transitionTo(
        SubmissionStatus $newStatus,
        ?User $changedBy = null,
        ?string $comment = null,
        bool $visibleToStudent = true
    ): self {
        return app(SubmissionStateMachine::class)
            ->transition($this, $newStatus, $changedBy, $comment, $visibleToStudent);
    }

    // ── Scopes ──────────────────────────────────────
    public function scopePending($query)
    {
        return $query->whereNotIn('status', ['completed', 'rejected', 'cancelled', 'draft']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('sla_deadline', '<', now())
                     ->whereNotIn('status', ['completed', 'rejected', 'cancelled']);
    }

    public function scopeForDepartment($query, int $deptId)
    {
        return $query->where('department_id', $deptId);
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeAssignedTo($query, int $adminId)
    {
        return $query->where('assigned_to', $adminId);
    }

    // ── Display helpers ──────────────────────────────
    /**
     * Return student info — respects anonymity.
     * Pass $forSuperAdmin = true to bypass the mask.
     */
    public function studentInfo(bool $forSuperAdmin = false): array
    {
        if ($this->is_anonymous && !$forSuperAdmin) {
            return ['name' => 'Anonymous', 'student_id' => null, 'phone' => null];
        }
        return [
            'name'       => $this->student?->name,
            'student_id' => $this->student?->student_id,
            'phone'      => $this->student?->phone,
        ];
    }
}
