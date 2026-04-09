<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Submission;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30; // seconds between retries

    public function __construct(
        private User $user,
        private Submission $submission,
        private string $type
    ) {}

    public function handle(SmsService $sms): void
    {
        [$title, $body] = $this->buildMessage();

        // In-app notification
        Notification::create([
            'user_id'       => $this->user->id,
            'submission_id' => $this->submission->id,
            'channel'       => 'in_app',
            'type'          => $this->type,
            'title'         => $title,
            'body'          => $body,
            'sent_at'       => now(),
        ]);

        // SMS notification (if user has phone number)
        if ($this->user->phone) {
            $smsRecord = Notification::create([
                'user_id'       => $this->user->id,
                'submission_id' => $this->submission->id,
                'channel'       => 'sms',
                'type'          => $this->type,
                'title'         => $title,
                'body'          => $body,
                'phone_number'  => $this->user->phone,
            ]);

            try {
                $sms->send($this->user->phone, $body);
                $smsRecord->update(['sent_at' => now()]);
            } catch (\Exception $e) {
                $smsRecord->update([
                    'failed_at'      => now(),
                    'failure_reason' => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildMessage(): array
    {
        $ref  = $this->submission->reference_no;
        $form = $this->submission->formType?->name ?? 'Request';

        return match ($this->type) {
            'submission.received' => [
                "Submission Received — {$ref}",
                "Your {$form} request ({$ref}) has been received and is being reviewed. You will be notified when there is an update.",
            ],
            'submission.in_review' => [
                "Under Review — {$ref}",
                "Your {$form} request ({$ref}) is now being reviewed by the concerned department.",
            ],
            'submission.approved' => [
                "Approved — {$ref}",
                "Good news! Your {$form} request ({$ref}) has been approved. Please log in to download your document.",
            ],
            'submission.rejected' => [
                "Not Approved — {$ref}",
                "Your {$form} request ({$ref}) was not approved. Please log in to see the reason and re-apply if eligible.",
            ],
            'submission.returned' => [
                "Action Required — {$ref}",
                "Your {$form} request ({$ref}) has been returned for more information. Please log in and update your submission.",
            ],
            'submission.sla_breach' => [
                "SLA Breach Alert — {$ref}",
                "ALERT: Submission {$ref} ({$form}) has exceeded its SLA deadline and requires immediate attention.",
            ],
            'submission.sla_overdue' => [
                "Your Request is Delayed — {$ref}",
                "Your {$form} request ({$ref}) is taking longer than expected. We apologise for the delay. Our team is working on it.",
            ],
            'submission.critical_overdue' => [
                "CRITICAL: Overdue Submission — {$ref}",
                "CRITICAL ALERT: Submission {$ref} ({$form}) has been escalated for over 48 hours and is still unresolved. Immediate action required.",
            ],
            default => [
                "Update on {$ref}",
                "There is an update on your request ({$ref}). Please log in to StudentsHub to view details.",
            ],
        };
    }
}
