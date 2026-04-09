<?php

namespace App\Jobs;

use App\Mail\StudentStatusUpdate;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Handles a single immediate notification delivery on a given channel.
 * Queued to the 'notifications' queue (Redis-backed in production).
 *
 * Retry strategy: 3 attempts, backs off 30s → 120s → 300s.
 * After 3 failures the job moves to the 'failed_jobs' table for inspection.
 */
class DispatchNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 30;
    public array $backoff = [30, 120, 300];

    public function __construct(
        private readonly Model  $recipient,
        private readonly string $eventType,
        private readonly string $channel,
        private readonly array  $payload
    ) {}

    public function handle(SmsService $sms): void
    {
        match ($this->channel) {
            'email'  => $this->sendEmail(),
            'sms'    => $this->sendSms($sms),
            'inapp'  => $this->storeInApp(),
            default  => Log::warning("[DispatchNotification] Unknown channel: {$this->channel}"),
        };
    }

    // ── Email ──────────────────────────────────────────────────────

    private function sendEmail(): void
    {
        $email = $this->recipient->email ?? null;
        if (!$email) {
            Log::warning('[DispatchNotification] No email on recipient', ['id' => $this->recipient->getKey()]);
            return;
        }

        $name    = $this->recipient->name ?? 'Student';
        $ref     = $this->payload['ref']          ?? '';
        $comment = $this->payload['admin_comment'] ?? null;
        $deadline = $this->payload['deadline']     ?? null;
        $portalUrl = config('app.student_portal_url', config('app.url') . '/portal');

        $mailable = new StudentStatusUpdate($name, $this->eventType, $ref, $portalUrl, $comment, $deadline);

        Mail::to($email)->send($mailable);

        $this->updateLogStatus('sent');

        Log::channel('notifications')->info('[Email sent]', [
            'to'    => $email,
            'event' => $this->eventType,
            'ref'   => $ref,
        ]);
    }

    // ── SMS ───────────────────────────────────────────────────────

    private function sendSms(SmsService $sms): void
    {
        $phone = $this->recipient->phone ?? null;
        if (!$phone) {
            Log::warning('[DispatchNotification] No phone on recipient', ['id' => $this->recipient->getKey()]);
            return;
        }

        $name       = $this->recipient->name ?? 'Student';
        $ref        = $this->payload['ref']      ?? '';
        $portalUrl  = config('app.student_portal_url', config('app.url') . '/portal');

        $message = match ($this->eventType) {
            'action_required', 'submission_returned' => SmsService::actionRequiredMessage(
                $name,
                $ref,
                $this->payload['deadline'] ?? 'as soon as possible',
                $portalUrl
            ),
            'sla_warning' => SmsService::slaWarningMessage(
                $ref,
                $this->payload['time_left'] ?? 'unknown',
                config('app.admin_dashboard_url', config('app.url') . '/admin')
            ),
            default => SmsService::studentStatusMessage(
                $name,
                $ref,
                $this->payload['status_label'] ?? ucwords(str_replace('_', ' ', $this->eventType)),
                $portalUrl
            ),
        };

        $result = $sms->send($phone, $message);

        $this->updateLogStatus($result['success'] ? 'sent' : 'failed', $result['message_id'] ?? null);

        if (!$result['success']) {
            Log::error('[DispatchNotification] SMS failed', [
                'phone' => $phone,
                'error' => $result['error'],
                'event' => $this->eventType,
            ]);
            // Re-throw so the job retries
            throw new \RuntimeException("SMS delivery failed: {$result['error']}");
        }
    }

    // ── In-app ────────────────────────────────────────────────────

    private function storeInApp(): void
    {
        // Insert into in_app_notifications table (read by the portal's notification bell)
        DB::table('in_app_notifications')->insert([
            'notifiable_id'   => $this->recipient->getKey(),
            'notifiable_type' => get_class($this->recipient),
            'event_type'      => $this->eventType,
            'reference'       => $this->payload['ref']          ?? null,
            'title'           => $this->payload['notif_title']   ?? $this->defaultInAppTitle(),
            'body'            => $this->payload['notif_body']    ?? '',
            'read'            => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->updateLogStatus('sent');
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function defaultInAppTitle(): string
    {
        return match ($this->eventType) {
            'submission_confirmed'  => 'Submission received',
            'submission_in_review'  => 'Under review',
            'action_required'       => 'Action required',
            'submission_returned'   => 'Form returned',
            'submission_approved'   => 'Approved',
            'submission_rejected'   => 'Rejected',
            'certificate_ready'     => 'Document ready',
            'admin_comment'         => 'New admin comment',
            'new_submission'        => 'New submission',
            'sla_warning'           => 'SLA warning',
            'sla_breach'            => 'SLA breached',
            default                 => 'Update',
        };
    }

    private function updateLogStatus(string $status, ?string $providerId = null): void
    {
        DB::table('notification_log')
            ->where('notifiable_id',   $this->recipient->getKey())
            ->where('notifiable_type', get_class($this->recipient))
            ->where('event_type',      $this->eventType)
            ->where('channel',         $this->channel)
            ->where('status',          'queued')
            ->orderByDesc('id')
            ->limit(1)
            ->update([
                'status'              => $status,
                'provider_message_id' => $providerId,
                'updated_at'          => now(),
            ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[DispatchNotification] Job permanently failed', [
            'recipient' => $this->recipient->getKey(),
            'event'     => $this->eventType,
            'channel'   => $this->channel,
            'error'     => $exception->getMessage(),
        ]);

        DB::table('notification_log')
            ->where('notifiable_id',   $this->recipient->getKey())
            ->where('notifiable_type', get_class($this->recipient))
            ->where('event_type',      $this->eventType)
            ->where('channel',         $this->channel)
            ->where('status',          'queued')
            ->orderByDesc('id')
            ->limit(1)
            ->update(['status' => 'failed', 'updated_at' => now()]);
    }
}
