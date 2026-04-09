<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Submission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ref', 'user_id', 'form_type_id', 'department_id',
        'assigned_admin_id', 'status', 'is_anonymous',
        'data', 'admin_comment', 'return_reason',
        'response_deadline', 'submitted_at', 'sla_deadline', 'completed_at',
    ];

    protected $casts = [
        'data'              => 'array',
        'is_anonymous'      => 'boolean',
        'submitted_at'      => 'datetime',
        'sla_deadline'      => 'datetime',
        'completed_at'      => 'datetime',
        'response_deadline' => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function formType(): BelongsTo
    {
        return $this->belongsTo(FormType::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SubmissionDocument::class);
    }

    public function auditEntries(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'reference', 'ref');
    }

    // ── Status helpers ────────────────────────────────────────────

    public const STATUS_FLOW = [
        'draft', 'submitted', 'routed', 'in_review',
        'action_required', 'escalated',
        'approved', 'rejected', 'returned', 'completed',
    ];

    public const TERMINAL_STATUSES = ['approved', 'rejected', 'completed'];

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES);
    }

    public function isSlaBreached(): bool
    {
        return $this->sla_deadline && now()->isAfter($this->sla_deadline)
               && !$this->isTerminal();
    }

    public function slaHoursLeft(): ?float
    {
        if (!$this->sla_deadline || $this->isTerminal()) {
            return null;
        }
        return round(now()->diffInMinutes($this->sla_deadline, false) / 60, 1);
    }

    // ── Ref generation ────────────────────────────────────────────

    public static function generateRef(): string
    {
        $year  = now()->format('Y');
        $count = self::whereYear('created_at', $year)->count() + 1;
        return sprintf('DIU-%s-%04d', $year, $count);
    }
}
