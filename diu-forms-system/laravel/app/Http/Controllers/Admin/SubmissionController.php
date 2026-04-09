<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Models\User;
use App\Services\SubmissionStateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
    public function __construct(
        private readonly SubmissionStateMachine $stateMachine
    ) {}

    // ── Queue (filterable, paginated) ────────────────
    public function index(Request $request): JsonResponse
    {
        $admin = $request->user();

        $query = Submission::with(['formType', 'student', 'department', 'assignedAdmin'])
            ->when(!$admin->isSuperAdmin(), fn ($q) =>
                // Regular admin only sees their own department
                $q->where('department_id', $admin->department_id)
            )
            ->when($request->status,   fn ($q) => $q->where('status', $request->status))
            ->when($request->category, fn ($q) => $q->whereHas('formType', fn ($fq) =>
                $fq->where('category', $request->category)
            ))
            ->when($request->sla === 'breach', fn ($q) => $q->overdue())
            ->when($request->sla === 'warn',   fn ($q) =>
                $q->whereBetween('sla_deadline', [now(), now()->addHours(4)])
                  ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
            )
            ->when($request->search, fn ($q) => $q->where(function ($sq) use ($request) {
                $sq->where('reference_no', 'like', "%{$request->search}%")
                   ->orWhereHas('student', fn ($uq) =>
                       $uq->where('name', 'like', "%{$request->search}%")
                          ->orWhere('student_id', 'like', "%{$request->search}%")
                   )
                   ->orWhereHas('formType', fn ($fq) =>
                       $fq->where('name', 'like', "%{$request->search}%")
                   );
            }))
            ->when($request->assigned_to_me, fn ($q) => $q->assignedTo($admin->id))
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_dir ?? 'desc');

        $submissions = $query->paginate($request->per_page ?? 25);

        return response()->json($submissions->through(fn ($s) => $this->formatSubmission($s, $admin)));
    }

    // ── Detail ──────────────────────────────────────
    public function show(Request $request, string $ref): JsonResponse
    {
        $admin      = $request->user();
        $submission = $this->findOrFail($ref, $admin);

        return response()->json([
            'submission'    => $this->formatSubmission($submission, $admin, detailed: true),
            'history'       => $submission->statusHistory()->with('changedBy')->get(),
            'documents'     => $submission->documents()->with('uploadedBy')->get(),
            'comments'      => $submission->comments()
                                ->with('user')
                                ->when(!$admin, fn ($q) => $q->where('is_internal', false))
                                ->get(),
            'approval_records' => $submission->approvalRecords()->with(['workflowStep', 'approver'])->get(),
        ]);
    }

    // ── Approve ──────────────────────────────────────
    public function approve(Request $request, string $ref): JsonResponse
    {
        $request->validate(['comment' => 'nullable|string|max:1000']);

        $submission = $this->findOrFail($ref, $request->user());

        $updated = $this->stateMachine->transition(
            $submission,
            SubmissionStatus::Approved,
            $request->user(),
            $request->comment
        );

        // If this form auto-generates a doc, queue that job
        if ($submission->formType->auto_generate_doc) {
            \App\Jobs\GenerateSubmissionDocument::dispatch($updated);
        }

        return response()->json([
            'message'    => 'Submission approved successfully.',
            'submission' => $this->formatSubmission($updated->fresh(), $request->user()),
        ]);
    }

    // ── Reject ───────────────────────────────────────
    public function reject(Request $request, string $ref): JsonResponse
    {
        $request->validate([
            'comment' => 'required|string|min:10|max:1000',  // reason is mandatory
        ]);

        $submission = $this->findOrFail($ref, $request->user());

        $updated = $this->stateMachine->transition(
            $submission,
            SubmissionStatus::Rejected,
            $request->user(),
            $request->comment
        );

        return response()->json([
            'message'    => 'Submission rejected.',
            'submission' => $this->formatSubmission($updated, $request->user()),
        ]);
    }

    // ── Return to student ─────────────────────────────
    public function returnToStudent(Request $request, string $ref): JsonResponse
    {
        $request->validate([
            'comment'  => 'required|string|min:10|max:1000',
            'deadline' => 'nullable|date|after:today',
        ]);

        $submission = $this->findOrFail($ref, $request->user());

        $updated = $this->stateMachine->transition(
            $submission,
            SubmissionStatus::ActionRequired,
            $request->user(),
            $request->comment
        );

        return response()->json([
            'message'    => 'Submission returned to student for correction.',
            'submission' => $this->formatSubmission($updated, $request->user()),
        ]);
    }

    // ── Escalate ─────────────────────────────────────
    public function escalate(Request $request, string $ref): JsonResponse
    {
        $request->validate(['comment' => 'required|string|min:10|max:1000']);

        $submission = $this->findOrFail($ref, $request->user());

        $updated = $this->stateMachine->transition(
            $submission,
            SubmissionStatus::Escalated,
            $request->user(),
            $request->comment
        );

        return response()->json([
            'message'    => 'Submission escalated.',
            'submission' => $this->formatSubmission($updated, $request->user()),
        ]);
    }

    // ── Assign ───────────────────────────────────────
    public function assign(Request $request, string $ref): JsonResponse
    {
        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $submission = $this->findOrFail($ref, $request->user());
        $admin      = User::findOrFail($request->assigned_to);

        $submission->update(['assigned_to' => $admin->id]);

        // Move to In Review if not already
        if ($submission->status === SubmissionStatus::Routed) {
            $this->stateMachine->transition(
                $submission,
                SubmissionStatus::InReview,
                $request->user(),
                "Assigned to {$admin->name}.",
                visibleToStudent: false
            );
        }

        return response()->json(['message' => "Assigned to {$admin->name}."]);
    }

    // ── Mark In Review ───────────────────────────────
    public function markInReview(Request $request, string $ref): JsonResponse
    {
        $submission = $this->findOrFail($ref, $request->user());

        $updated = $this->stateMachine->transition(
            $submission,
            SubmissionStatus::InReview,
            $request->user(),
        );

        return response()->json(['message' => 'Marked as In Review.']);
    }

    // ── Add Comment ──────────────────────────────────
    public function addComment(Request $request, string $ref): JsonResponse
    {
        $request->validate([
            'body'        => 'required|string|max:2000',
            'is_internal' => 'boolean',
        ]);

        $submission = $this->findOrFail($ref, $request->user());

        $comment = $submission->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => $request->body,
            'is_internal' => $request->boolean('is_internal', false),
        ]);

        return response()->json($comment->load('user'), 201);
    }

    // ── Internal Notes ───────────────────────────────
    public function updateInternalNotes(Request $request, string $ref): JsonResponse
    {
        $request->validate(['notes' => 'nullable|string|max:5000']);

        $submission = $this->findOrFail($ref, $request->user());
        $submission->update(['internal_notes' => $request->notes]);

        return response()->json(['message' => 'Internal notes updated.']);
    }

    // ── Upload Document ──────────────────────────────
    public function uploadDocument(Request $request, string $ref): JsonResponse
    {
        $request->validate([
            'file'        => 'required|file|max:5120|mimes:pdf,jpg,jpeg,png',
            'description' => 'nullable|string|max:255',
            'is_public'   => 'boolean',
        ]);

        $submission = $this->findOrFail($ref, $request->user());
        $file       = $request->file('file');
        $path       = $file->store("submissions/{$submission->id}/admin", 'local');

        $doc = $submission->documents()->create([
            'uploaded_by'   => $request->user()->id,
            'file_name'     => $file->getClientOriginalName(),
            'file_path'     => $path,
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
            'document_type' => 'admin_upload',
            'description'   => $request->description,
            'is_public'     => $request->boolean('is_public', true),
            'created_at'    => now(),
        ]);

        return response()->json($doc, 201);
    }

    // ── Generate Document ─────────────────────────────
    public function generateDocument(Request $request, string $ref): JsonResponse
    {
        $submission = $this->findOrFail($ref, $request->user());

        if (!$submission->formType->auto_generate_doc) {
            return response()->json(['error' => 'This form type does not support auto-generation.'], 422);
        }

        \App\Jobs\GenerateSubmissionDocument::dispatch($submission);

        return response()->json(['message' => 'Document generation queued.']);
    }

    // ── Bulk Actions ─────────────────────────────────
    public function bulk(Request $request): JsonResponse
    {
        $request->validate([
            'ids'    => 'required|array|min:1|max:50',
            'ids.*'  => 'exists:submissions,id',
            'action' => 'required|in:assign,mark_read',
            'assigned_to' => 'required_if:action,assign|exists:users,id',
        ]);

        $count = 0;
        foreach ($request->ids as $id) {
            $submission = Submission::find($id);
            if (!$submission) continue;

            if ($request->action === 'assign') {
                $submission->update(['assigned_to' => $request->assigned_to]);
                $count++;
            }
        }

        return response()->json(['message' => "{$count} submissions updated."]);
    }

    // ── Helpers ──────────────────────────────────────
    private function findOrFail(string $ref, User $admin): Submission
    {
        $query = Submission::with(['formType', 'student', 'department', 'assignedAdmin'])
            ->where('reference_no', $ref);

        // Scope to admin's department unless super_admin
        if (!$admin->isSuperAdmin()) {
            $query->where('department_id', $admin->department_id);
        }

        return $query->firstOrFail();
    }

    private function formatSubmission(Submission $s, User $admin, bool $detailed = false): array
    {
        $isSuperAdmin = $admin->isSuperAdmin();

        $data = [
            'id'           => $s->id,
            'reference_no' => $s->reference_no,
            'form_type'    => $s->formType->name,
            'category'     => $s->formType->category,
            'department'   => $s->department->name,
            'status'       => $s->status->value,
            'status_label' => $s->status->label(),
            'sla_status'   => $s->slaStatus(),
            'sla_deadline' => $s->sla_deadline?->toIso8601String(),
            'sla_hours_remaining' => $s->slaRemainingHours(),
            'student'      => $s->studentInfo($isSuperAdmin),
            'assigned_to'  => $s->assignedAdmin?->only(['id', 'name']),
            'submitted_at' => $s->submitted_at?->toIso8601String(),
            'is_anonymous' => $s->is_anonymous,
        ];

        if ($detailed) {
            $data['form_data']      = $s->form_data;
            $data['internal_notes'] = $s->internal_notes;
            $data['output_document']= $s->output_document;
            $data['current_step']   = $s->current_step;
        }

        return $data;
    }
}
