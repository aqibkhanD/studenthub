<?php

namespace App\Services;

use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

class PdfGenerationService
{
    /**
     * Generate a PDF for the given approved submission.
     * Saves to private storage and records the path on the submission.
     *
     * @throws \RuntimeException if the form type does not support generation
     */
    public function generate(Submission $submission): string
    {
        if (!$submission->formType->auto_generate_doc) {
            throw new \RuntimeException(
                "Form type [{$submission->formType->slug}] does not support auto-generation."
            );
        }

        $data = $this->resolveTemplateData($submission);
        $html = $this->renderTemplate($submission->formType->slug, $data);
        $pdf  = $this->buildPdf($html);

        $path = $this->savePdf($pdf, $submission);

        // Record on submission and log an audit entry
        $submission->update(['output_document' => $path]);

        activity()
            ->performedOn($submission)
            ->withProperties(['path' => $path])
            ->log('pdf_generated');

        return $path;
    }

    /**
     * Return a temporary signed URL for downloading the generated PDF.
     * URL expires in 30 minutes — prevents URL guessing.
     */
    public function signedDownloadUrl(Submission $submission): string
    {
        return \URL::temporarySignedRoute(
            'submissions.download',
            now()->addMinutes(30),
            ['ref' => $submission->reference_no]
        );
    }

    /**
     * Stream the PDF file contents for download.
     */
    public function streamPdf(Submission $submission): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (!$submission->output_document || !Storage::disk('local')->exists($submission->output_document)) {
            abort(404, 'Document not yet generated.');
        }

        $filename = Str::slug($submission->formType->name) . '-' . $submission->reference_no . '.pdf';

        return Storage::disk('local')->download(
            $submission->output_document,
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    // ── Template data resolver ────────────────────────────────────────────────

    /**
     * Map submission data to the flat array the Blade templates expect.
     * Keeps all field-mapping logic out of templates.
     */
    private function resolveTemplateData(Submission $submission): array
    {
        $student   = $submission->student;
        $formType  = $submission->formType;
        $dept      = $submission->department;
        $formData  = $submission->form_data ?? [];
        $signatory = $this->signatory($dept->slug);

        $base = [
            // Submission meta
            'reference_no'   => $submission->reference_no,
            'issue_date'     => now()->format('d F Y'),
            'verify_url'     => route('verify.certificate', $submission->reference_no),
            'verify_code'    => $this->verificationCode($submission->reference_no),

            // Student
            'student_name'   => $student->name,
            'student_id'     => $student->student_id,
            'program'        => $student->program      ?? 'N/A',
            'department'     => $student->program      // use student's dept, not routing dept
                                    ? $this->programDepartment($student->program)
                                    : $dept->name,
            'semester'       => $student->semester     ?? 'N/A',
            'batch'          => $student->batch        ?? 'N/A',

            // University
            'university'     => 'Daffodil International University',
            'uni_address'    => 'Birulia, Savar, Dhaka-1216, Bangladesh',
            'uni_phone'      => '+880-2-9138234',
            'uni_email'      => 'info@diu.edu.bd',
            'uni_web'        => 'www.daffodilvarsity.edu.bd',

            // Signatory
            'signatory'      => $signatory,
        ];

        // Merge form-type-specific fields
        return array_merge($base, $this->formTypeFields($formType->slug, $formData, $student));
    }

    private function formTypeFields(string $slug, array $formData, $student): array
    {
        return match ($slug) {
            'bonafide-certificate' => [
                'purpose' => $formData['purpose']      ?? 'official purposes',
                'session' => $this->academicSession($student->batch ?? null),
                'addressed_to' => $formData['addressed_to'] ?? null,
            ],
            'completion-letter' => [
                'degree'          => $student->program ?? 'N/A',
                'session'         => $this->academicSession($student->batch ?? null),
                'cgpa'            => $formData['cgpa']             ?? 'To be confirmed',
                'completion_date' => $formData['completion_date']  ?? 'N/A',
            ],
            'internship-letter' => [
                'addressed_to' => $formData['addressed_to'] ?? 'The Concerned Authority',
                'org_address'  => $formData['org_address']  ?? '',
                'duration'     => $formData['duration']     ?? 'the required period',
                'start_date'   => $formData['start_date']   ?? 'N/A',
                'focus_area'   => $formData['focus_area']   ?? 'the relevant field',
            ],
            default => [],
        };
    }

    // ── Blade rendering ───────────────────────────────────────────────────────

    private function renderTemplate(string $slug, array $data): string
    {
        $viewMap = [
            'bonafide-certificate' => 'pdf.bonafide',
            'completion-letter'    => 'pdf.completion',
            'internship-letter'    => 'pdf.internship',
        ];

        $view = $viewMap[$slug] ?? null;

        if (!$view || !\View::exists($view)) {
            throw new \RuntimeException("No PDF template found for form type [{$slug}].");
        }

        return view($view, $data)->render();
    }

    // ── mPDF construction ─────────────────────────────────────────────────────

    private function buildPdf(string $html): Mpdf
    {
        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'margin_left'    => 22,
            'margin_right'   => 22,
            'margin_top'     => 18,
            'margin_bottom'  => 22,
            'margin_header'  => 8,
            'margin_footer'  => 8,
            'orientation'    => 'P',
            'setAutoTopMargin' => 'pad',
        ]);

        // Custom fonts directory for future Bangla support (SolaimanLipi etc.)
        // $mpdf->AddFontDirectory(resource_path('fonts'));

        $mpdf->SetTitle('DIU Certificate');
        $mpdf->SetAuthor('Daffodil International University');
        $mpdf->SetCreator('DIU Student Services Portal');
        $mpdf->SetDisplayMode('fullpage');

        // Prevent content copy (optional security)
        // $mpdf->SetProtection(['print', 'print-highres'], '', Str::random(32));

        $mpdf->WriteHTML($html);

        return $mpdf;
    }

    // ── Storage ───────────────────────────────────────────────────────────────

    private function savePdf(Mpdf $mpdf, Submission $submission): string
    {
        $year = now()->format('Y');
        $dir  = "generated/{$year}/{$submission->id}";
        $file = Str::slug($submission->formType->name) . '.pdf';
        $path = "{$dir}/{$file}";

        Storage::disk('local')->makeDirectory($dir);
        Storage::disk('local')->put($path, $mpdf->Output('', 'S'));

        return $path;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function signatory(string $deptSlug): array
    {
        return config("signatories.{$deptSlug}", config('signatories.registrar'));
    }

    private function verificationCode(string $referenceNo): string
    {
        return strtoupper(substr(hash('sha256', $referenceNo . config('app.key')), 0, 12));
    }

    /**
     * Derive academic session from student batch number.
     * Batch 55 enrolled in 2022 → session "2022–2026" (4-year programme).
     */
    private function academicSession(?string $batch): string
    {
        if (!$batch || !is_numeric($batch)) return 'N/A';
        $start = 2022 - (55 - (int) $batch);   // approximate; adjust formula to DIU's numbering
        return "{$start}–" . ($start + 4);
    }

    /**
     * Extract department name from program string.
     * "B.Sc. in Computer Science and Engineering" → "Computer Science and Engineering"
     */
    private function programDepartment(string $program): string
    {
        if (preg_match('/\bin\b\s+(.+)$/i', $program, $m)) {
            return trim($m[1]);
        }
        return $program;
    }
}
