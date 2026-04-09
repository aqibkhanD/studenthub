<?php

namespace App\Console\Commands;

use App\Jobs\SendNotificationJob;
use App\Models\SlaEscalationRule;
use App\Models\Submission;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SlaMonitorCommand extends Command
{
    protected $signature   = 'sla:monitor';
    protected $description = 'Check SLA deadlines and trigger escalations for overdue submissions';

    public function __construct(private AuditService $audit)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->info('[SLA Monitor] Running at ' . now()->toDateTimeString());

        $breached = Submission::with(['student', 'formType', 'department'])
            ->whereNotIn('status', ['approved', 'rejected', 'completed', 'cancelled'])
            ->where('sla_deadline', '<', now())
            ->whereNull('escalated_at') // not yet escalated
            ->get();

        $this->info("[SLA Monitor] Found {$breached->count()} submissions past SLA deadline.");

        foreach ($breached as $submission) {
            $this->escalate($submission);
        }

        // Re-check already-escalated ones to see if they need level-2 escalation
        $this->handleLevelTwo();

        $this->info('[SLA Monitor] Done.');
    }

    private function escalate(Submission $submission): void
    {
        DB::transaction(function () use ($submission) {
            // Capture current status BEFORE update (getOriginal() returns new value after update)
            $fromStatus = $submission->status;

            // Mark as escalated
            $submission->update([
                'status'       => Submission::STATUS_ESCALATED,
                'escalated_at' => now(),
            ]);

            // Log status change
            $submission->statusHistory()->create([
                'from_status'           => $fromStatus,
                'to_status'             => Submission::STATUS_ESCALATED,
                'comment'               => 'Automatically escalated: SLA deadline exceeded.',
                'is_visible_to_student' => true,
                'changed_at'            => now(),
            ]);

            $this->audit->log(null, 'submission.sla_escalated', 'Submission', $submission->id);

            // Find escalation rules for this department / form type
            $rules = SlaEscalationRule::where('department_id', $submission->department_id)
                ->where(fn($q) => $q->whereNull('form_type_id')->orWhere('form_type_id', $submission->form_type_id))
                ->where('escalation_level', 1)
                ->get();

            if ($rules->isEmpty()) {
                // Fall back: notify department head if set
                $head = $submission->department?->head;
                if ($head) {
                    SendNotificationJob::dispatch($head, $submission, 'submission.sla_breach');
                }
            } else {
                foreach ($rules as $rule) {
                    // Escalate to specific user or dept head
                    $target = $rule->escalateTo ?? $submission->department?->head;
                    if ($target) {
                        SendNotificationJob::dispatch($target, $submission, 'submission.sla_breach');
                    }

                    // Optionally notify student too
                    if ($rule->notify_student && $submission->student && !$submission->is_anonymous) {
                        SendNotificationJob::dispatch($submission->student, $submission, 'submission.sla_overdue');
                    }
                }
            }

            $this->line("  Escalated: {$submission->reference_no} (dept: {$submission->department?->name})");
        });
    }

    /**
     * For already-escalated submissions that are still open after 2x the original SLA,
     * trigger a level-2 escalation to the super admin.
     */
    private function handleLevelTwo(): void
    {
        $critical = Submission::with(['student', 'formType', 'department'])
            ->where('status', Submission::STATUS_ESCALATED)
            ->whereNotNull('escalated_at')
            ->whereRaw("escalated_at < now() - interval '48 hours'") // still open 48h after escalation
            ->get();

        if ($critical->isEmpty()) return;

        $this->info("[SLA Monitor] {$critical->count()} level-2 escalations needed.");

        $superAdmins = User::where('role', 'super_admin')->where('is_active', true)->get();

        foreach ($critical as $submission) {
            foreach ($superAdmins as $admin) {
                SendNotificationJob::dispatch($admin, $submission, 'submission.critical_overdue');
            }
            $this->line("  Critical: {$submission->reference_no}");
        }
    }
}
