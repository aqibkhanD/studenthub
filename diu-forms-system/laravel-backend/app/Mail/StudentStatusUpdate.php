<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic status-update email sent to students.
 * Covers: confirmed, in_review, action_required, returned, approved, rejected, certificate_ready, admin_comment.
 *
 * Each event type maps to a distinct subject line and accent colour.
 * The single Blade template renders conditional sections based on $eventType.
 */
class StudentStatusUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public string $studentName;
    public string $eventType;
    public string $subject;
    public string $accentColor;
    public string $headingText;
    public string $bodyText;
    public ?string $ctaLabel;
    public ?string $ctaUrl;
    public ?string $adminComment;
    public ?string $deadline;
    public string  $ref;
    public string  $unsubscribeUrl;

    // ── Event type metadata ────────────────────────────────────────
    private const EVENT_META = [
        'submission_confirmed'  => ['#1d4ed8', 'Submission Received',           'Your request has been received and is being processed.'],
        'submission_in_review'  => ['#0369a1', 'Your Form Is Being Reviewed',   'An admin is currently reviewing your submission.'],
        'action_required'       => ['#b45309', 'Action Required',               'Your submission has been returned and requires your attention.'],
        'submission_returned'   => ['#b45309', 'Form Returned for Changes',     'Please review the admin feedback and resubmit your form.'],
        'submission_approved'   => ['#15803d', 'Submission Approved',           'Your request has been approved. Please log in for next steps.'],
        'submission_rejected'   => ['#b91c1c', 'Submission Rejected',           'Unfortunately, your submission could not be approved at this time.'],
        'certificate_ready'     => ['#15803d', 'Your Document Is Ready',        'Your certificate/document has been generated and is ready to download.'],
        'admin_comment'         => ['#4338ca', 'New Comment on Your Submission', 'An admin has added a comment to your submission.'],
    ];

    public function __construct(
        string  $studentName,
        string  $eventType,
        string  $ref,
        string  $portalUrl,
        ?string $adminComment = null,
        ?string $deadline = null
    ) {
        $meta = self::EVENT_META[$eventType] ?? ['#1d4ed8', 'Update on Your Submission', 'Your submission status has been updated.'];

        $this->studentName    = $studentName;
        $this->eventType      = $eventType;
        $this->ref            = $ref;
        $this->accentColor    = $meta[0];
        $this->headingText    = $meta[1];
        $this->bodyText       = $meta[2];
        $this->adminComment   = $adminComment;
        $this->deadline       = $deadline;
        $this->ctaLabel       = 'View My Submission';
        $this->ctaUrl         = $portalUrl;
        $this->unsubscribeUrl = $portalUrl . '/unsubscribe?token=' . $this->generateUnsubToken($studentName);
        $this->subject        = "[DIU Student Services] {$meta[1]} — {$ref}";
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from:    new \Illuminate\Mail\Mailables\Address(
                         config('mail.from.address'),
                         config('mail.from.name')
                     ),
            subject: $this->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.student.status-update',
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function generateUnsubToken(string $seed): string
    {
        return hash_hmac('sha256', $seed . now()->format('Y-m'), config('app.key'));
    }
}
