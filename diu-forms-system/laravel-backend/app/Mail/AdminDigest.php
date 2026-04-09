<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Batched digest email sent to admins.
 * Aggregates multiple pending_digest rows into a single email.
 *
 * Example payload for a single digest item:
 * [
 *   'event_type' => 'new_submission',
 *   'ref'        => 'DIU-2024-0042',
 *   'title'      => 'Bonafide Certificate',
 *   'student'    => 'Md. Ariful Islam',
 *   'department' => 'CSE',
 *   'created_at' => '2024-01-15 10:23',
 * ]
 */
class AdminDigest extends Mailable
{
    use Queueable, SerializesModels;

    public string $adminName;
    public string $delivery;          // 'digest_hourly' | 'digest_daily'
    public array  $items;             // array of decoded payload objects
    public int    $itemCount;
    public string $dashboardUrl;
    public string $unsubscribeUrl;
    public string $periodLabel;

    private const EVENT_LABELS = [
        'new_submission'       => 'New submission',
        'submission_resubmit'  => 'Resubmission',
        'setting_change'       => 'Setting changed',
        'admin_comment'        => 'Admin comment',
        'sla_warning'          => 'SLA warning',
    ];

    public function __construct(
        string $adminName,
        string $adminEmail,
        string $delivery,
        array  $items,
        string $dashboardUrl
    ) {
        $this->adminName     = $adminName;
        $this->delivery      = $delivery;
        $this->items         = $items;
        $this->itemCount     = count($items);
        $this->dashboardUrl  = $dashboardUrl;
        $this->periodLabel   = $delivery === 'digest_hourly' ? 'the last hour' : 'today';
        $this->unsubscribeUrl = $dashboardUrl . '/settings#notifications';
    }

    public function envelope(): Envelope
    {
        $label = $this->delivery === 'digest_hourly' ? 'Hourly' : 'Daily';
        return new Envelope(
            from:    new \Illuminate\Mail\Mailables\Address(
                         config('mail.from.address'),
                         config('mail.from.name')
                     ),
            subject: "[DIU Admin] {$label} Digest — {$this->itemCount} update" . ($this->itemCount !== 1 ? 's' : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.digest',
        );
    }

    public function attachments(): array
    {
        return [];
    }

    public function eventLabel(string $eventType): string
    {
        return self::EVENT_LABELS[$eventType] ?? ucwords(str_replace('_', ' ', $eventType));
    }
}
