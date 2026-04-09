<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubmissionStateMachine
{
    public function __construct(
        private readonly NotificationService $notifier
    ) {}

    /**
     * Perform a status transition.
     * Wraps everything in a DB transaction — history record, status update,
     * and notification all succeed or all roll back.
     *
     * @throws \InvalidArgumentException if the transition is not allowed
     */
    public function transition(
        Submission $submission,
        SubmissionStatus $newStatus,
        ?User $changedBy = null,
        ?string $comment = null,
        bool $visibleToStudent = true
    ): Submission {
        if (!$submission->status->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from [{$submission->status->value}] to [{$newStatus->value}] " .
                "on submission {$submission->reference_no}."
            );
        }

        DB::transaction(function () use ($submission, $newStatus, $changedBy, $comment, $visibleToStudent) {
            $fromStatus = $submission->status;

            // 1. Write audit history (append-only)
            $submission->statusHistory()->create([
                'changed_by'          => $changedBy?->id,
                'from_status'         => $fromStatus->value,
                'to_status'           => $newStatus->value,
                'comment'             => $comment,
                'is_visible_to_student' => $visibleToStudent,
                'step_number'         => $submission->current_step,
                'changed_at'          => now(),
            ]);

            // 2. Update submission status
            $updateData = ['status' => $newStatus];

            // Set timestamps for specific transitions
            if ($newStatus === SubmissionStatus::Submitted) {
                $updateData['submitted_at'] = now();
                $updateData['sla_deadline']  = now()->addHours(
                    $submission->formType->effectiveSlaHours()
                );
            }

            if ($newStatus === SubmissionStatus::Escalated) {
                $updateData['escalated_at'] = now();
            }

            if ($newStatus->isTerminal()) {
                $updateData['resolved_at'] = now();
            }

            $submission->update($updateData);

            // 3. Handle side-effects per transition
            $this->handleSideEffects($submission, $fromStatus, $newStatus, $changedBy, $comment);
        });

        Log::info("Submission {$submission->reference_no} transitioned: " .
                  "{$submission->getOriginal('status')} → {$newStatus->value}");

        return $submission->fresh();
    }

    /**
     * Side-effects triggered by specific transitions.
     */
    private function handleSideEffects(
        Submission $submission,
        SubmissionStatus $from,
        SubmissionStatus $to,
        ?User $changedBy,
        ?string $comment
    ): void {
        // Auto-route on submission
        if ($to === SubmissionStatus::Submitted) {
            $this->autoRoute($submission);
        }

        // Notify student on every visible change
        $studentNotifyStatuses = [
            SubmissionStatus::Routed,
            SubmissionStatus::InReview,
            SubmissionStatus::ActionRequired,
            SubmissionStatus::Approved,
            SubmissionStatus::Rejected,
            SubmissionStatus::Returned,
            SubmissionStatus::Completed,
            SubmissionStatus::Escalated,
        ];

        if (in_array($to, $studentNotifyStatuses) && $submission->student) {
            $this->notifier->notifyStudent($submission, $to, $comment);
        }

        // Notify dept admins on new submission
        if ($to === SubmissionStatus::Routed) {
            $this->notifier->notifyDepartment($submission);
        }

        // Notify assigned admin when escalated
        if ($to === SubmissionStatus::Escalated) {
            $this->notifier->notifyEscalation($submission);
        }

        // Create approval records for multi-step workflows
        if ($to === SubmissionStatus::Routed) {
            $this->createApprovalRecords($submission);
        }
    }

    /**
     * Auto-route: set department and move to Routed.
     */
    private function autoRoute(Submission $submission): void
    {
        // Department was set at creation from form_type.department_id.
        // Just advance the status.
        $this->transition(
            $submission,
            SubmissionStatus::Routed,
            changedBy: null,      // system-triggered
            comment:   'Automatically routed to ' . $submission->department->name,
            visibleToStudent: true
        );
    }

    /**
     * Pre-create approval record rows for multi-step workflows.
     */
    private function createApprovalRecords(Submission $submission): void
    {
        $workflow = $submission->formType->workflow;
        if (!$workflow || $workflow->isSingle()) return;

        foreach ($workflow->steps as $step) {
            $submission->approvalRecords()->firstOrCreate(
                ['workflow_step_id' => $step->id],
                ['action' => 'pending', 'created_at' => now()]
            );
        }
    }
}
