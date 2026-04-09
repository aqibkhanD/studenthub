<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use App\Services\AuditService;
use App\Services\FileUploadService;
use App\Services\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSubmissionController extends Controller
{
    public function __construct(
        private SubmissionService $submissions,
        private FileUploadService $uploads,
        private AuditService      $audit
    ) {}

    // GET /api/v1/admin/submissions
    public function index(Request $request): JsonResponse
    {
        $query = Submission::with(['formType:id,name,category', 'student:id,name,student_id,phone', 'department:id,name', 'assignedTo:id,name'])
            ->orderByDesc('submitted_at');

        // Dept admin sees only their department
        $user = $request->user();
        if ($user->role === 'admin' && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        // Filters
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('form_type_id')) $query->where('form_type_id', $request->form_type_id);
        if ($request->filled('assigned_to')) $query->where('assigned_to', $request->assigned_to);
        if ($request->filled('date_from'))   $query->whereDate('submitted_at', '>=', $request->date_from);
        if ($request->filled('date_to'))     $query->whereDate('submitted_at', '<=', $request->date_to);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('reference_no', 'ilike', "%{$s}%")
                  ->orWhereHas('student', fn($q2) => $q2->where('name', 'ilike', "%{$s}%")
                      ->orWhere('student_id', 'ilike', "%{$s}%"));
            });
        }
        if ($request->boolean('sla_breached')) {
            $query->where('sla_deadline', '<', now())
                  ->whereNotIn('status', ['approved','rejected','completed','cancelled']);
        }

        $submissions = $query->paginate($request->integer('per_page', 20));

        return response()->json($submissions);
    }

    // GET /api/v1/admin/submissions/{ref}
    public function show(Request $request, string $ref): JsonResponse
    {
        $submission = $this->findForAdmin($request, $ref);
        $submission->load([
            'formType.fields', 'student', 'department', 'assignedTo:id,name',
            'statusHistory.changedBy:id,name,role',
            'documents.uploadedBy:id,name,role',
            'comments' => fn($q) => $q->with('user:id,name,role')->orderBy('created_at'),
            'approvalRecords.workflowStep', 'approvalRecords.approver:id,name',
        ]);

        return response()->json(['submission' => $submission]);
    }

    // PUT /api/v1/admin/submissions/{ref}/status
    public function updateStatus(Request $request, string $ref): JsonResponse
    {
        $data = $request->validate([
            'status'  => 'required|in:in_review,action_required,approved,rejected,returned,completed,escalated',
            'comment' => 'nullable|string|max:2000',
        ]);

        // Comment required when rejecting or returning
        if (in_array($data['status'], ['rejected', 'returned']) && empty($data['comment'])) {
            return response()->json(['message' => 'A comment is required when rejecting or returning a submission.'], 422);
        }

        $submission = $this->findForAdmin($request, $ref);

        $submission = $this->submissions->updateStatus(
            $submission,
            $data['status'],
            $request->user(),
            $data['comment'] ?? null
        );

        return response()->json([
            'message'    => "Status updated to {$data['status']}.",
            'submission' => $submission->fresh(['formType:id,name', 'department:id,name']),
        ]);
    }

    // PUT /api/v1/admin/submissions/{ref}/assign
    public function assign(Request $request, string $ref): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $submission = $this->findForAdmin($request, $ref);
        $submission->update(['assigned_to' => $data['user_id']]);

        $this->audit->log($request->user()->id, 'submission.assigned', 'Submission', $submission->id,
            ['assigned_to' => $submission->assigned_to],
            ['assigned_to' => $data['user_id']]
        );

        return response()->json(['message' => 'Submission assigned.']);
    }

    // POST /api/v1/admin/submissions/{ref}/comments
    public function addComment(Request $request, string $ref): JsonResponse
    {
        $data = $request->validate([
            'body'        => 'required|string|max:2000',
            'is_internal' => 'boolean',
        ]);

        $submission = $this->findForAdmin($request, $ref);

        $comment = $submission->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => $data['body'],
            'is_internal' => $data['is_internal'] ?? false,
            'is_system'   => false,
        ]);

        return response()->json(['comment' => $comment->load('user:id,name,role')], 201);
    }

    // POST /api/v1/admin/submissions/{ref}/documents
    public function uploadDocument(Request $request, string $ref): JsonResponse
    {
        $request->validate([
            'document'    => 'required|file|max:20480', // frontend sends field name "document"
            'description' => 'nullable|string|max:255',
            'is_public'   => 'boolean',
        ]);

        $submission = $this->findForAdmin($request, $ref);

        $doc = $this->uploads->store(
            file:        $request->file('document'),
            submission:  $submission,
            uploader:    $request->user(),
            docType:     'admin_upload',
            description: $request->input('description'),
            isPublic:    $request->boolean('is_public', true)
        );

        return response()->json(['document' => $doc], 201);
    }

    // POST /api/v1/admin/submissions/bulk-status
    public function bulkStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ref_nos'   => 'required|array|min:1|max:100',
            'ref_nos.*' => 'required|string',
            'status'    => 'required|in:in_review,action_required,approved,rejected,returned,completed',
            'comment'   => 'nullable|string|max:2000',
        ]);

        // Require comment for reject/return
        if (in_array($data['status'], ['rejected', 'returned']) && empty($data['comment'])) {
            return response()->json(['message' => 'A comment is required when rejecting or returning submissions.'], 422);
        }

        $user    = $request->user();
        $updated = 0;
        $failed  = [];

        foreach ($data['ref_nos'] as $ref) {
            try {
                $query = Submission::where('reference_no', $ref);
                if ($user->role === 'admin' && $user->department_id) {
                    $query->where('department_id', $user->department_id);
                }
                $submission = $query->firstOrFail();

                $this->submissions->updateStatus($submission, $data['status'], $user, $data['comment'] ?? null);
                $updated++;
            } catch (\Throwable) {
                $failed[] = $ref;
            }
        }

        return response()->json([
            'updated' => $updated,
            'failed'  => $failed,
            'message' => "{$updated} submission(s) updated to {$data['status']}."
                . (count($failed) ? ' ' . count($failed) . ' could not be updated.' : ''),
        ]);
    }

    // GET /api/v1/admin/submissions/export
    public function export(Request $request): StreamedResponse
    {
        $user   = $request->user();
        $query  = Submission::with(['formType:id,name,category', 'student:id,name,student_id,phone', 'department:id,name', 'assignedTo:id,name'])
            ->orderByDesc('submitted_at');

        // Dept-scoped admin
        if ($user->role === 'admin' && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        // Apply same filters as index
        if ($request->filled('status'))        $query->where('status', $request->status);
        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('form_type_id'))  $query->where('form_type_id', $request->form_type_id);
        if ($request->filled('date_from'))     $query->whereDate('submitted_at', '>=', $request->date_from);
        if ($request->filled('date_to'))       $query->whereDate('submitted_at', '<=', $request->date_to);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('reference_no', 'ilike', "%{$s}%")
                  ->orWhereHas('student', fn($q2) => $q2->where('name', 'ilike', "%{$s}%")
                      ->orWhere('student_id', 'ilike', "%{$s}%"));
            });
        }
        if ($request->boolean('sla_breached')) {
            $query->where('sla_deadline', '<', now())
                  ->whereNotIn('status', ['approved', 'rejected', 'completed', 'cancelled']);
        }

        $filename = 'submissions_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            fwrite($out, "\xEF\xBB\xBF");

            // Header row
            fputcsv($out, [
                'Reference No', 'Form Type', 'Category', 'Student Name', 'Student ID',
                'Student Phone', 'Department', 'Status', 'Assigned To',
                'Submitted At', 'SLA Deadline', 'SLA Breached', 'Is Anonymous',
            ]);

            // Stream rows in chunks to avoid memory issues
            $query->chunk(200, function ($submissions) use ($out) {
                foreach ($submissions as $s) {
                    fputcsv($out, [
                        $s->reference_no,
                        $s->formType?->name ?? '',
                        $s->formType?->category ?? '',
                        $s->is_anonymous ? 'Anonymous' : ($s->student?->name ?? ''),
                        $s->is_anonymous ? '' : ($s->student?->student_id ?? ''),
                        $s->is_anonymous ? '' : ($s->student?->phone ?? ''),
                        $s->department?->name ?? '',
                        $s->status,
                        $s->assignedTo?->name ?? '',
                        $s->submitted_at?->format('Y-m-d H:i') ?? '',
                        $s->sla_deadline?->format('Y-m-d H:i') ?? '',
                        $s->isSlaBreached() ? 'Yes' : 'No',
                        $s->is_anonymous ? 'Yes' : 'No',
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ----------------------------------------------------------
    private function findForAdmin(Request $request, string $ref): Submission
    {
        $query = Submission::where('reference_no', $ref);

        // Dept-scoped admins can only see their own department's submissions
        $user = $request->user();
        if ($user->role === 'admin' && $user->department_id) {
            $query->where('department_id', $user->department_id);
        }

        return $query->firstOrFail();
    }
}
