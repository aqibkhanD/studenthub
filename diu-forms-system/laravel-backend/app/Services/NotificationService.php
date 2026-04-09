<?php

namespace App\Services;

use App\Jobs\DispatchNotificationJob;
use App\Models\Admin;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Central orchestration layer for all outbound notifications.
 *
 * Usage:
 *   app(NotificationService::class)->dispatch($student, 'submission_confirmed', [
 *       'ref'    => 'DIU-2024-0042',
 *       'title'  => 'Bonafide Certificate',
 *       'status' => 'submitted',
 *   ]);
 *
 * The service will:
 *  1. Look up the recipient's preferences for (event_type, each channel)
 *  2. Apply spam-prevention rules (rate limits, duplicate suppression, quiet hours)
 *  3. Route immediate events straight to DispatchNotificationJob (queued, non-blocking)
 *  4. Route digest events to pending_digests table (picked up by ProcessDigestJob)
 *  5. Write every outcome (sent / suppressed) to notification_log
 */
class NotificationService
{
    // ── Spam prevention constants ──────────────────────────────────
    const MAX_EMAILS_PER_HOUR    = 10;
    const MAX_SMS_PER_DAY        = 5;
    const DUPLICATE_WINDOW_MIN   = 30;   // minutes
    const QUIET_START_HOUR       = 22;   // BST
    const QUIET_END_HOUR         = 7;
    const QUIET_END_MIN          = 30;

    /**
     * Dispatch a notification to a recipient across all configured channels.
     *
     * @param  Model  $recipient  App\Models\User (student) or App\Models\Admin
     * @param  string $eventType  e.g. 'submission_confirmed'
     * @param  array  $payload    Context data — ref, title, status, comment, deadline, etc.
     */
    public function dispatch(Model $recipient, string $eventType, array $payload): void
    {
        $channels = ['email', 'sms', 'inapp'];

        foreach ($channels as $channel) {
            $delivery = $this->resolveDelivery($recipient, $eventType, $channel);

            if ($delivery === 'never') {
                continue;
            }

            $suppression = $this->checkSuppression($recipient, $eventType, $channel, $delivery);

            if ($suppression) {
                $this->log($recipient, $eventType, $channel, $delivery, 'suppressed', $payload, $suppression);
                continue;
            }

            if ($delivery === 'immediate') {
                DispatchNotificationJob::dispatch($recipient, $eventType, $channel, $payload)
                    ->onQueue('notifications');
                $this->log($recipient, $eventType, $channel, $delivery, 'queued', $payload);
            } else {
                // digest_hourly or digest_daily — store for batch pickup
                DB::table('pending_digests')->insert([
                    'notifiable_id'   => $recipient->getKey(),
                    'notifiable_type' => get_class($recipient),
                    'event_type'      => $eventType,
                    'channel'         => $channel,
                    'delivery'        => $delivery,
                    'reference'       => $payload['ref'] ?? null,
                    'payload'         => json_encode($payload),
                    'dispatched'      => false,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);
            }
        }
    }

    // ── Preference resolution ──────────────────────────────────────

    /**
     * Resolve the delivery mode for a (recipient, event, channel) triple.
     * Falls back to system defaults when no user preference is set.
     */
    private function resolveDelivery(Model $recipient, string $eventType, string $channel): string
    {
        $pref = DB::table('notification_preferences')
            ->where('notifiable_type', get_class($recipient))
            ->where('notifiable_id',   $recipient->getKey())
            ->where('event_type',      $eventType)
            ->where('channel',         $channel)
            ->value('delivery');

        return $pref ?? $this->systemDefault($recipient, $eventType, $channel);
    }

    /**
     * System-level defaults when no preference row exists.
     * Students: email+SMS+inapp immediate for most events; inapp-only for in_review.
     * Admins:   new_submission → digest; SLA/escalation → immediate; own actions → never.
     */
    private function systemDefault(Model $recipient, string $eventType, string $channel): string
    {
        $isAdmin = $recipient instanceof Admin;

        if ($isAdmin) {
            return match (true) {
                in_array($eventType, ['new_submission', 'submission_resubmit', 'setting_change']) => ($channel === 'email' ? 'digest_hourly' : ($channel === 'inapp' ? 'immediate' : 'never')),
                in_array($eventType, ['sla_warning', 'sla_breach', 'escalation'])                => 'immediate',
                in_array($eventType, ['role_change', 'new_admin'])                               => ($channel === 'sms' ? 'never' : 'immediate'),
                default => 'immediate',
            };
        }

        // Student defaults
        return match (true) {
            $eventType === 'submission_in_review'  => ($channel === 'inapp' ? 'immediate' : 'never'),
            in_array($eventType, ['submission_confirmed', 'action_required', 'submission_returned',
                                  'submission_approved',  'submission_rejected', 'certificate_ready']) => 'immediate',
            $eventType === 'admin_comment'         => ($channel === 'sms' ? 'never' : 'immediate'),
            default => 'immediate',
        };
    }

    // ── Spam prevention ────────────────────────────────────────────

    /**
     * Returns null if the notification may proceed, or a suppression reason string if blocked.
     */
    private function checkSuppression(Model $recipient, string $eventType, string $channel, string $delivery): ?string
    {
        $id   = $recipient->getKey();
        $type = get_class($recipient);

        // 1. Global channel unsubscribe (stored on user model)
        if ($channel === 'email' && method_exists($recipient, 'hasUnsubscribedEmail') && $recipient->hasUnsubscribedEmail()) {
            return 'unsubscribed:email';
        }
        if ($channel === 'sms' && method_exists($recipient, 'hasUnsubscribedSms') && $recipient->hasUnsubscribedSms()) {
            return 'unsubscribed:sms';
        }

        // 2. Super-admin individual submission emails disabled by default
        if ($channel === 'email' && $recipient instanceof Admin && $recipient->role === 'super_admin') {
            $noIndividualEvents = ['new_submission', 'submission_resubmit', 'submission_in_review'];
            if (in_array($eventType, $noIndividualEvents) && $delivery === 'immediate') {
                return 'super_admin:individual_email_disabled';
            }
        }

        // 3. Quiet hours — suppress SMS and non-urgent emails
        if (in_array($channel, ['sms', 'email']) && $delivery !== 'immediate') {
            if ($this->isQuietHours()) {
                return 'quiet_hours';
            }
        }
        // SLA/escalation are urgent; skip quiet hours for those
        if ($channel === 'sms' && $delivery === 'immediate') {
            $urgentEvents = ['sla_warning', 'sla_breach', 'escalation', 'action_required'];
            if (!in_array($eventType, $urgentEvents) && $this->isQuietHours()) {
                return 'quiet_hours';
            }
        }

        // 4. Email rate limit: max 10 per hour per recipient
        if ($channel === 'email') {
            $cacheKey = "notif_rate_email:{$type}:{$id}";
            $count = Cache::get($cacheKey, 0);
            if ($count >= self::MAX_EMAILS_PER_HOUR) {
                return 'rate_limit:email_hourly';
            }
            Cache::put($cacheKey, $count + 1, now()->addHour());
        }

        // 5. SMS rate limit: max 5 per day per recipient
        if ($channel === 'sms') {
            $cacheKey = "notif_rate_sms:{$type}:{$id}";
            $count = Cache::get($cacheKey, 0);
            if ($count >= self::MAX_SMS_PER_DAY) {
                return 'rate_limit:sms_daily';
            }
            Cache::put($cacheKey, $count + 1, now()->endOfDay());
        }

        // 6. Duplicate suppression: same event+channel within 30 minutes
        if (in_array($channel, ['email', 'sms'])) {
            $dupeKey = "notif_dupe:{$type}:{$id}:{$eventType}:{$channel}";
            if (Cache::has($dupeKey)) {
                return 'duplicate:30min';
            }
            Cache::put($dupeKey, 1, now()->addMinutes(self::DUPLICATE_WINDOW_MIN));
        }

        return null;
    }

    /**
     * Check if the current time (Bangladesh Standard Time, UTC+6) falls in quiet hours.
     * Default window: 22:00 – 07:30.
     */
    private function isQuietHours(): bool
    {
        $now = Carbon::now('Asia/Dhaka');
        $h   = $now->hour;
        $m   = $now->minute;

        if ($h >= self::QUIET_START_HOUR) {
            return true;
        }
        if ($h < self::QUIET_END_HOUR) {
            return true;
        }
        if ($h === self::QUIET_END_HOUR && $m < self::QUIET_END_MIN) {
            return true;
        }
        return false;
    }

    // ── Audit logging ──────────────────────────────────────────────

    private function log(
        Model   $recipient,
        string  $eventType,
        string  $channel,
        string  $delivery,
        string  $status,
        array   $payload,
        ?string $suppressionReason = null
    ): void {
        DB::table('notification_log')->insert([
            'notifiable_id'      => $recipient->getKey(),
            'notifiable_type'    => get_class($recipient),
            'event_type'         => $eventType,
            'channel'            => $channel,
            'delivery'           => $delivery,
            'status'             => $status,
            'reference'          => $payload['ref'] ?? null,
            'payload'            => json_encode($payload),
            'suppression_reason' => $suppressionReason,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }
}
