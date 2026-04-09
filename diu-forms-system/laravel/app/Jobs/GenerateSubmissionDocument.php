<?php

namespace App\Jobs;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Services\NotificationService;
use App\Services\PdfGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSubmissionDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry up to 3 times with a 60-second backoff.
     */
    public int $tries   = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly Submission $submission
    ) {}

    public function handle(
        PdfGenerationService $pdfService,
        NotificationService  $notifier
    ): void {
        // Guard: only generate for approved submissions with auto_generate_doc
        if (!$this->submission->formType->auto_generate_doc) {
            Log::warning("GenerateSubmissionDocument called on non-auto-generate form [{$this->submission->reference_no}]. Skipping.");
            return;
        }

        if ($this->submission->status !== SubmissionStatus::Approved) {
            Log::warning("GenerateSubmissionDocument: submission [{$this->submission->reference_no}] is not in Approved state. Skipping.");
            return;
        }

        try {
            $path = $pdfService->generate($this->submission);

            Log::info("PDF generated for [{$this->submission->reference_no}] at [{$path}].");

            // Advance to Completed and notify student
            $this->submission->transitionTo(
                SubmissionStatus::Completed,
                changedBy:        null,
                comment:          'Document generated and ready for download.',
                visibleToStudent: true
            );

            // Explicit notification with download prompt
            if ($this->submission->student) {
                $notifier->notifyDocumentReady($this->submission);
            }

        } catch (\Throwable $e) {
            Log::error("PDF generation failed for [{$this->submission->reference_no}]: {$e->getMessage()}");
            $this->fail($e);
        }
    }

    /**
     * Handle a job that has failed all retry attempts.
     */
    public function failed(\Throwable $e): void
    {
        Log::critical(
            "GenerateSubmissionDocument permanently failed for [{$this->submission->reference_no}]. " .
            "Manual intervention required. Error: {$e->getMessage()}"
        );

        // Notify a super-admin via in-app notification
        \App\Models\User::where('role', 'super_admin')
            ->each(function ($admin) use ($e) {
                \App\Models\Notification::create([
                    'user_id'       => $admin->id,
                    'submission_id' => $this->submission->id,
                    'channel'       => 'in_app',
                    'type'          => 'pdf_generation.failed',
                    'title'         => 'PDF Generation Failed',
                    'body'          => "Failed to generate document for {$this->submission->reference_no}. " .
                                      "Error: {$e->getMessage()}",
                    'sent_at'       => now(),
                ]);
            });
    }
}
