<?php

namespace App\Http\Controllers\Student;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Services\ReferenceNumberService;
use App\Services\SubmissionStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function __construct(
        private readonly ReferenceNumberService $refService,
        private readonly SubmissionStateMachine  $stateMachine,
    ) {}

    // ── My Submissions ────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $submissions = Submission::with(['formType', 'department'])
            ->forStudent($request->user()->id)
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($submissions->through(fn ($s) => [
            'reference_no'  => $s->reference_no,
            'form_type'     => $s->formType->name,
            'category'      => $s->formType->category,
            'department'    => $s->department->name,
            'status'        => $s->status->value,
            'status_label'  => $s->status->label(),
            'sla_status'    => $s->slaStatus(),
            'submitted_at'  => $s->submitted_at?->toIso8601String(),
            'updated_at'    => $s->updated_at->toIso8601String(),
        ]));
    }

    // ── Show Detail ───────────────────────────────────
    public function show(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::with(['formType', 'department', 'assignedAdmin'])
            ->where('reference_no', $ref)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'reference_no'  => $submission->reference_no,
            'form_type'     => $submission->formType->name,
            'department'    => $submission->department->name,
            'status'        => $submission->status->value,
            'status_label'  => $submission->status->label(),
            'sla_deadline'  => $submission->sla_deadline?->toIso8601String(),
            'sla_status'    => $submission->slaStatus(),
            'form_data'     => $submission->form_data,
            'submitted_at'  => $submission->submitted_at?->toIso8601String(),
            'output_document' => $submission->output_document,
            // History visible to student (excludes internal actions)
            'history' => $submission->statusHistory()
                ->where('is_visible_to_student', true)
                ->get()
                ->map(fn ($h) => [
                    'to_status' => $h->to_status,
                    'comment'   => $h->comment,
                    'changed_at'=> $h->changed_at->toIso8601String(),
                ]),
            'documents' => $submission->studentDocuments()->get()->map(fn ($d) => [
                'file_name' => $d->file_name,
                'url'       => $d->url(),
                'size'      => $d->humanSize(),
            ]),
            'comments' => $submission->publicComments()->with('user:id,name,role')->get(),
        ]);
    }

    // ── Create Submission ────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'form_type_id'  => 'required|exists:form_types,id',
            'form_data'     => 'required|array',
            'is_anonymous'  => 'boolean',
            'as_draft'      => 'boolean',
        ]);

        $formType = \App\Models\FormType::findOrFail($request->form_type_id);

        // Enforce anonymous only on allowed form types
        $isAnon = $request->boolean('is_anonymous') && $formType->allow_anonymous;

        $submission = Submission::create([
            'reference_no'  => $this->refService->generate(),
            'form_type_id'  => $formType->id,
            'student_id'    => $request->user()->id,
            'is_anonymous'  => $isAnon,
            'department_id' => $formType->department_id,
            'status'        => SubmissionStatus::Draft,
            'form_data'     => $request->form_data,
            'current_step'  => 1,
        ]);

        // Unless saving as draft, submit immediately
        if (!$request->boolean('as_draft')) {
            $submission = $this->stateMachine->transition(
                $submission,
                SubmissionStatus::Submitted,
                $request->user()
            );
        }

        return response()->json([
            'message'      => $request->boolean('as_draft')
                ? 'Draft saved successfully.'
                : 'Submission received. You will be notified of updates.',
            'reference_no' => $submission->reference_no,
            'status'       => $submission->status->value,
        ], 201);
    }

    // ── Resubmit (after Return/Action Required) ──────
    public function update(Request $request, string $ref): JsonResponse
    {
        $request->validate([
            'form_data' => 'required|array',
        ]);

        $submission = Submission::where('reference_no', $ref)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        if ($submission->status !== SubmissionStatus::ActionRequired) {
            return response()->json(['error' => 'Only submissions in "Action Required" state can be resubmitted.'], 422);
        }

        $submission->update(['form_data' => $request->form_data]);

        $updated = $this->stateMachine->transition(
            $submission,
            SubmissionStatus::Submitted,
            $request->user(),
            'Student resubmitted after correction.'
        );

        return response()->json([
            'message'      => 'Resubmitted successfully.',
            'reference_no' => $updated->reference_no,
        ]);
    }

    // ── Cancel Draft ─────────────────────────────────
    public function cancel(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('reference_no', $ref)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        if ($submission->status !== SubmissionStatus::Draft) {
            return response()->json(['error' => 'Only draft submissions can be cancelled.'], 422);
        }

        $this->stateMachine->transition(
            $submission,
            SubmissionStatus::Cancelled,
            $request->user()
        );

        return response()->json(['message' => 'Submission cancelled.']);
    }

    // ── Upload Document ──────────────────────────────
    public function uploadDocument(Request $request, string $ref): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png',
        ]);

        $submission = Submission::where('reference_no', $ref)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        // Only allow uploads on active (non-terminal) submissions
        if ($submission->status->isTerminal()) {
            return response()->json(['error' => 'Cannot upload to a closed submission.'], 422);
        }

        $file = $request->file('file');
        $path = $file->store("submissions/{$submission->id}/student", 'local');

        $doc = $submission->documents()->create([
            'uploaded_by'   => $request->user()->id,
            'file_name'     => $file->getClientOriginalName(),
            'file_path'     => $path,
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
            'document_type' => 'student_upload',
            'is_public'     => true,
            'created_at'    => now(),
        ]);

        return response()->json(['message' => 'Document uploaded.', 'document' => $doc], 201);
    }

    // ── Comments (student side) ──────────────────────
    public function comments(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('reference_no', $ref)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        return response()->json(
            $submission->publicComments()->with('user:id,name,role')->get()
        );
    }

    public function addComment(Request $request, string $ref): JsonResponse
    {
        $request->validate(['body' => 'required|string|max:1000']);

        $submission = Submission::where('reference_no', $ref)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        if ($submission->status->isTerminal()) {
            return response()->json(['error' => 'Cannot comment on a closed submission.'], 422);
        }

        $comment = $submission->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => $request->body,
            'is_internal' => false,
        ]);

        return response()->json($comment, 201);
    }
}
