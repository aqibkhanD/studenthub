<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\Notification;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    // ── Student Notifications ────────────────────────

    public function notifyStudent(
        Submission $submission,
        SubmissionStatus $newStatus,
        ?string $adminComment = null
    ): void {
        if (!$submission->student) return;

        $message = $this->buildStudentMessage($submission, $newStatus, $adminComment);

        // In-app notification
        $this->createInAppNotification(
            user:         $submission->student,
            submission:   $submission,
            type:         "submission.{$newStatus->value}",
            title:        $this->statusTitle($newStatus),
            body:         $message,
        );

        // SMS notification
        if ($submission->student->phone) {
            $this->sendSms($submission->student->phone, $message);
        }
    }

    // ── Department Notifications ─────────────────────

    public function notifyDepartment(Submission $submission): void
    {
        $admins = User::where('department_id', $submission->department_id)
                      ->where('role', 'admin')
                      ->where('is_active', true)
                      ->get();

        $title = "New submission: {$submission->reference_no}";
        $body  = "A new {$submission->formType->name} request has been submitted" .
                 ($submission->is_anonymous ? " (anonymous)." : " by a student.");

        foreach ($admins as $admin) {
            $this->createInAppNotification(
                user:       $admin,
                submission: $submission,
                type:       'submission.new',
                title:      $title,
                body:       $body,
            );
        }
    }

    // ── Escalation Notifications ─────────────────────

    public function notifyEscalation(Submission $submission): void
    {
        // Notify dept head
        if ($head = $submission->department->head) {
            $this->createInAppNotification(
                user:       $head,
                submission: $submission,
                type:       'submission.escalated',
                title:      "🚨 Escalation: {$submission->reference_no}",
                body:       "Submission {$submission->reference_no} has been escalated " .
                            "due to SLA breach. Requires immediate attention.",
            );

            if ($head->phone) {
                $this->sendSms(
                    $head->phone,
                    "DIU ALERT: Submission {$submission->reference_no} escalated (SLA breach). " .
                    "Please review urgently."
                );
            }
        }
    }

    // ── SLA Warning Notifications (called by scheduler) ──

    public function sendSlaWarnings(): void
    {
        // Find submissions within 4 hours of SLA breach
        $at_risk = \App\Models\Submission::query()
            ->whereBetween('sla_deadline', [now(), now()->addHours(4)])
            ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
            ->with(['student', 'formType', 'assignedAdmin'])
            ->get();

        foreach ($at_risk as $submission) {
            $hoursLeft = round($submission->slaRemainingHours(), 1);

            // Notify assigned admin
            if ($submission->assignedAdmin) {
                $this->createInAppNotification(
                    user:       $submission->assignedAdmin,
                    submission: $submission,
                    type:       'sla.warning',
                    title:      "⏰ SLA Warning: {$submission->reference_no}",
                    body:       "This submission expires in {$hoursLeft}h.",
                );
            }
        }
    }

    // ── Internal helpers ─────────────────────────────

    private function createInAppNotification(
        User       $user,
        Submission $submission,
        string     $type,
        string     $title,
        string     $body
    ): Notification {
        return Notification::create([
            'user_id'       => $user->id,
            'submission_id' => $submission->id,
            'channel'       => 'in_app',
            'type'          => $type,
            'title'         => $title,
            'body'          => $body,
            'sent_at'       => now(),
        ]);
    }

    /**
     * Send SMS via SSL Wireless Bangladesh gateway.
     * Docs: https://developer.sslwireless.com/
     */
    private function sendSms(string $phone, string $message): void
    {
        $apiToken  = config('services.ssl_wireless.token');
        $sid       = config('services.ssl_wireless.sid');
        $csmsId    = 'DIU-' . now()->format('YmdHis') . rand(100, 999);

        try {
            $response = Http::timeout(10)->post('https://smsplus.sslwireless.com/api/v3/send-sms', [
                'api_token' => $apiToken,
                'sid'       => $sid,
                'sms'       => $message,
                'msisdn'    => $this->normalisePhone($phone),
                'csms_id'   => $csmsId,
            ]);

            if (!$response->successful()) {
                Log::warning("SMS failed for {$phone}: " . $response->body());
                $this->logSmsFailure($phone, $message, $response->body());
            } else {
                Log::info("SMS sent to {$phone} [csms_id: {$csmsId}]");
            }
        } catch (\Throwable $e) {
            Log::error("SMS exception for {$phone}: " . $e->getMessage());
            $this->logSmsFailure($phone, $message, $e->getMessage());
        }
    }

    /** Normalise BD phone to 88XXXXXXXXXXX format */
    private function normalisePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0'))  $phone = '88' . $phone;
        if (!str_starts_with($phone, '88')) $phone = '88' . $phone;
        return $phone;
    }

    private function logSmsFailure(string $phone, string $message, string $reason): void
    {
        // Store failed SMS for retry — can be picked up by a scheduled job
        Notification::create([
            'channel'        => 'sms',
            'type'           => 'sms.failed',
            'title'          => 'SMS Failed',
            'body'           => $message,
            'phone_number'   => $phone,
            'failed_at'      => now(),
            'failure_reason' => substr($reason, 0, 500),
        ]);
    }

    private function buildStudentMessage(
        Submission $submission,
        SubmissionStatus $status,
        ?string $comment
    ): string {
        $ref  = $submission->reference_no;
        $type = $submission->formType->name;

        $base = match ($status) {
            SubmissionStatus::InReview       => "Your {$type} request ({$ref}) is now under review.",
            SubmissionStatus::Approved       => "Your {$type} request ({$ref}) has been APPROVED.",
            SubmissionStatus::Rejected       => "Your {$type} request ({$ref}) has been rejected.",
            SubmissionStatus::Returned       => "Your {$type} request ({$ref}) has been returned for correction.",
            SubmissionStatus::ActionRequired => "Action required on your request ({$ref}). Please check the portal.",
            SubmissionStatus::Completed      => "Your {$type} ({$ref}) is ready for collection.",
            SubmissionStatus::Escalated      => "Your request ({$ref}) has been escalated for urgent review.",
            default                          => "Update on your request ({$ref}): {$status->label()}.",
        };

        if ($comment) {
            $base .= " Note: " . substr($comment, 0, 100);
        }

        return $base . " - DIU Student Services";
    }

    private function statusTitle(SubmissionStatus $status): string
    {
        return match ($status) {
            SubmissionStatus::InReview       => '👀 Request Under Review',
            SubmissionStatus::Approved       => '✅ Request Approved',
            SubmissionStatus::Rejected       => '❌ Request Rejected',
            SubmissionStatus::Returned       => '↩️ Action Required',
            SubmissionStatus::ActionRequired => '⚠️ Action Required',
            SubmissionStatus::Completed      => '🎉 Request Completed',
            SubmissionStatus::Escalated      => '🚨 Request Escalated',
            default                          => 'Update on Your Request',
        };
    }
}
