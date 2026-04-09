<?php

namespace App\Services;

use App\Models\FormType;
use App\Models\Submission;
use App\Models\User;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\DB;

class SubmissionService
{
    public function __construct(private AuditService $audit) {}

    // ----------------------------------------------------------
    // Create a new submission (draft or direct submit)
    // ----------------------------------------------------------
    public function create(
        User $user,
        FormType $formType,
        array $formData,
        bool $isAnonymous = false,
        bool $submit = true
    ): Submission {
        return DB::transaction(function () use ($user, $formType, $formData, $isAnonymous, $submit) {

            $status = $submit ? Submission::STATUS_SUBMITTED : Submission::STATUS_DRAFT;

            $submission = Submission::create([
                'reference_no'  => $submit ? $this->generateReferenceNo() : null,
                'form_type_id'  => $formType->id,
                'student_id'    => $isAnonymous ? null : $user->id,
                'is_anonymous'  => $isAnonymous,
                'department_id' => $formType->department_id,
                'status'        => $status,
                'form_data'     => $formData,
                'submitted_at'  => $submit ? now() : null,
                'sla_deadline'  => $submit ? now()->addHours($formType->effectiveSlaHours()) : null,
            ]);

            // Record status history
            $submission->statusHistory()->create([
                'changed_by'             => $isAnonymous ? null : $user->id,
                'from_status'            => null,
                'to_status'              => $status,
                'is_visible_to_student'  => true,
                'changed_at'             => now(),
            ]);

            $this->audit->log($user->id, 'submission.created', 'Submission', $submission->id);

            // Dispatch notification
            if ($submit && !$isAnonymous) {
                SendNotificationJob::dispatch($user, $submission, 'submission.received');
            }

            return $submission;
        });
    }

    // ----------------------------------------------------------
    // Re-submit a returned or draft submission
    // ----------------------------------------------------------
    public function resubmit(Submission $submission, array $formData, User $user): Submission
    {
        return DB::transaction(function () use ($submission, $formData, $user) {
            $oldStatus = $submission->status;

            $submission->update([
                'status'       => Submission::STATUS_SUBMITTED,
                'form_data'    => $formData,
                'submitted_at' => now(),
                'sla_deadline' => now()->addHours($submission->formType->effectiveSlaHours()),
            ]);

            $submission->statusHistory()->create([
                'changed_by'            => $user->id,
                'from_status'           => $oldStatus,
                'to_status'             => Submission::STATUS_SUBMITTED,
                'comment'               => 'Student re-submitted after return.',
                'is_visible_to_student' => true,
                'changed_at'            => now(),
            ]);

            $this->audit->log($user->id, 'submission.resubmitted', 'Submission', $submission->id);

            return $submission->fresh();
        });
    }

    // ----------------------------------------------------------
    // Cancel a draft
    // ----------------------------------------------------------
    public function cancel(Submission $submission, User $user): void
    {
        $submission->update(['status' => Submission::STATUS_CANCELLED]);
        $this->audit->log($user->id, 'submission.cancelled', 'Submission', $submission->id);
    }

    // ----------------------------------------------------------
    // Admin: update submission status
    // ----------------------------------------------------------
    public function updateStatus(
        Submission $submission,
        string $newStatus,
        User $admin,
        ?string $comment = null
    ): Submission {
        return DB::transaction(function () use ($submission, $newStatus, $admin, $comment) {
            $oldStatus = $submission->status;

            $updates = ['status' => $newStatus];

            if (in_array($newStatus, [Submission::STATUS_APPROVED, Submission::STATUS_COMPLETED])) {
                $updates['resolved_at'] = now();
            }
            if ($newStatus === Submission::STATUS_ESCALATED) {
                $updates['escalated_at'] = now();
            }

            $submission->update($updates);

            $submission->statusHistory()->create([
                'changed_by'            => $admin->id,
                'from_status'           => $oldStatus,
                'to_status'             => $newStatus,
                'comment'               => $comment,
                'is_visible_to_student' => true,
                'changed_at'            => now(),
            ]);

            $this->audit->log(
                $admin->id,
                "submission.status_changed.{$newStatus}",
                'Submission',
                $submission->id,
                ['status' => $oldStatus],
                ['status' => $newStatus]
            );

            // Notify student
            $student = $submission->student;
            if ($student && !$submission->is_anonymous) {
                $type = match($newStatus) {
                    Submission::STATUS_APPROVED   => 'submission.approved',
                    Submission::STATUS_REJECTED   => 'submission.rejected',
                    Submission::STATUS_RETURNED   => 'submission.returned',
                    Submission::STATUS_IN_REVIEW  => 'submission.in_review',
                    default                       => 'submission.updated',
                };
                SendNotificationJob::dispatch($student, $submission, $type);
            }

            return $submission->fresh();
        });
    }

    // ----------------------------------------------------------
    // Generate reference number: DIU-2026-00421
    // ----------------------------------------------------------
    private function generateReferenceNo(): string
    {
        $year = now()->year;

        $seq = DB::transaction(function () use ($year) {
            $row = DB::table('reference_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row) {
                DB::table('reference_sequences')
                    ->where('year', $year)
                    ->increment('last_sequence');
                return $row->last_sequence + 1;
            } else {
                DB::table('reference_sequences')->insert([
                    'year'          => $year,
                    'last_sequence' => 1,
                ]);
                return 1;
            }
        });

        return sprintf('DIU-%d-%05d', $year, $seq);
    }
}
