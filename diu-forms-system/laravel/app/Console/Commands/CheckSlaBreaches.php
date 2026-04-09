<?php

namespace App\Console\Commands;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Services\NotificationService;
use App\Services\SubmissionStateMachine;
use Illuminate\Console\Command;

/**
 * Runs every 30 minutes via the scheduler.
 * 1. Sends SLA warning notifications (< 4h remaining)
 * 2. Auto-escalates any submission that has breached its SLA
 */
class CheckSlaBreaches extends Command
{
    protected $signature   = 'sla:check';
    protected $description = 'Check SLA deadlines, send warnings, and auto-escalate breaches.';

    public function __construct(
        private readonly NotificationService   $notifier,
        private readonly SubmissionStateMachine $stateMachine,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // 1. Warn admins about submissions approaching SLA
        $this->notifier->sendSlaWarnings();
        $this->info('SLA warning notifications sent.');

        // 2. Auto-escalate breached submissions not yet escalated
        $breached = Submission::overdue()
            ->whereNotIn('status', [
                SubmissionStatus::Escalated->value,
                SubmissionStatus::Approved->value,
            ])
            ->with(['formType', 'department'])
            ->get();

        $count = 0;
        foreach ($breached as $submission) {
            try {
                $this->stateMachine->transition(
                    $submission,
                    SubmissionStatus::Escalated,
                    changedBy:  null,
                    comment:    "Auto-escalated: SLA deadline passed at {$submission->sla_deadline->format('d M Y H:i')}.",
                    visibleToStudent: true
                );
                $count++;
            } catch (\Throwable $e) {
                $this->warn("Could not escalate {$submission->reference_no}: {$e->getMessage()}");
            }
        }

        $this->info("Auto-escalated {$count} submission(s).");
        return Command::SUCCESS;
    }
}
