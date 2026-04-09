<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Draft           = 'draft';
    case Submitted       = 'submitted';
    case Routed          = 'routed';
    case InReview        = 'in_review';
    case ActionRequired  = 'action_required';
    case Escalated       = 'escalated';
    case Approved        = 'approved';
    case Rejected        = 'rejected';
    case Returned        = 'returned';
    case Completed       = 'completed';
    case Cancelled       = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft          => 'Draft',
            self::Submitted      => 'Submitted',
            self::Routed         => 'Routed',
            self::InReview       => 'In Review',
            self::ActionRequired => 'Action Required',
            self::Escalated      => 'Escalated',
            self::Approved       => 'Approved',
            self::Rejected       => 'Rejected',
            self::Returned       => 'Returned',
            self::Completed      => 'Completed',
            self::Cancelled      => 'Cancelled',
        };
    }

    /** Terminal states — no further transitions allowed */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Rejected,
            self::Cancelled,
        ]);
    }

    /** States visible on the student-facing timeline */
    public function isVisibleToStudent(): bool
    {
        return !in_array($this, [self::Draft]);
    }

    /**
     * Allowed next states from the current state.
     * This is the single source of truth for the state machine.
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::Draft          => [self::Submitted, self::Cancelled],
            self::Submitted      => [self::Routed, self::Cancelled],
            self::Routed         => [self::InReview, self::Returned, self::Escalated],
            self::InReview       => [self::Approved, self::Rejected, self::Returned, self::Escalated, self::ActionRequired],
            self::ActionRequired => [self::Submitted, self::Cancelled],  // student re-submits
            self::Escalated      => [self::InReview, self::Approved, self::Rejected],
            self::Approved       => [self::Completed],
            // Terminal states have no transitions
            self::Rejected,
            self::Returned,
            self::Completed,
            self::Cancelled      => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions());
    }
}
