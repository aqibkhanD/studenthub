<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FormType;
use App\Models\Submission;
use App\Models\SubmissionDocument;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SubmissionController extends Controller
{
    public function __construct(private readonly NotificationService $notifier) {}

    // ── Student endpoints ──────────────────────────────────────────

    /**
     * GET /api/student/submissions
     * List the authenticated student's own submissions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Submission::where('user_id', $request->user()->id)
            ->with(['formType:id,name,category,slug', 'documents:id,submission_id,original_name,size_bytes'])
            ->latest('updated_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * GET /api/student/submissions/{ref}
     */
    public function show(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('ref', $ref)
            ->where('user_id', $request->user()->id)
            ->with(['formType', 'department:id,name', 'documents'])
            ->firstOrFail();

        return response()->json($submission);
    }

    /**
     * POST /api/student/submissions
     * Create a new submission (or save as draft).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'form_type_slug' => 'required|string|exists:form_types,slug',
            'data'           => 'required|array',
            'is_anonymous'   => 'boolean',
            'is_draft'       => 'boolean',
        ]);

        $formType = FormType::where('slug', $request->form_type_slug)
                            ->where('is_active', true)
                            ->firstOrFail();

        // Dynamic field validation
        $this->validateFormData($request->data, $formType->fields);

        $isDraft = (bool) $request->input('is_draft', false);

        $submission = DB::transaction(function () use ($request, $formType, $isDraft) {
            $now = $isDraft ? null : now();

            $submission = Submission::create([
                'ref'           => Submission::generateRef(),
                'user_id'       => $request->user()->id,
                'form_type_id'  => $formType->id,
                'department_id' => $formType->department_id,
                'status'        => $isDraft ? 'draft' : 'submitted',
                'is_anonymous'  => $request->boolean('is_anonymous') && $formType->allow_anonymous,
                'data'          => $request->data,
                'submitted_at'  => $now,
                'sla_deadline'  => $now?->addHours($formType->sla_hours),
            ]);

            if (!$isDraft) {
                AuditLog::record(
                    $request->user(), 'submission',
                    "Submitted {$formType->name}",
                    $submission->ref
                );
            }

            return $submission;
        });

        if (!$isDraft) {
            // Notify student: confirmed
            $this->notifier->dispatch($request->user(), 'submission_confirmed', [
                'ref'           => $submission->ref,
                'title'         => $formType->name,
                'status_label'  => 'Submitted',
                'notif_title'   => 'Submission received',
                'notif_body'    => "Your {$formType->name} request ({$submission->ref}) has been received.",
            ]);
        }

        return response()->json($submission->load('formType'), 201);
    }

    /**
     * PATCH /api/student/submissions/{ref}
     * Update a draft OR resubmit a returned form.
     */
    public function update(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('ref', $ref)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (!in_array($submission->status, ['draft', 'returned'])) {
            return response()->json(['message' => 'This submission cannot be edited.'], 422);
        }

        $request->validate([
            'data'     => 'required|array',
            'is_draft' => 'boolean',
        ]);

        $isDraft   = (bool) $request->input('is_draft', false);
        $wasReturn = $submission->status === 'returned';

        $submission->update([
            'data'         => $request->data,
            'status'       => $isDraft ? 'draft' : 'submitted',
            'submitted_at' => $isDraft ? $submission->submitted_at : now(),
            'sla_deadline' => $isDraft ? $submission->sla_deadline
                                       : now()->addHours($submission->formType->sla_hours),
        ]);

        if (!$isDraft) {
            AuditLog::record(
                $request->user(),
                'status_change',
                $wasReturn ? 'Student resubmitted returned form' : 'Draft submitted',
                $submission->ref,
                ['field' => 'status', 'from' => $wasReturn ? 'returned' : 'draft', 'to' => 'submitted']
            );

            $this->notifier->dispatch($request->user(), 'submission_confirmed', [
                'ref'          => $submission->ref,
                'title'        => $submission->formType->name,
                'status_label' => 'Resubmitted',
                'notif_title'  => 'Resubmission received',
                'notif_body'   => "Your updated {$submission->formType->name} ({$submission->ref}) has been resubmitted.",
            ]);
        }

        return response()->json($submission);
    }

    // ── Admin endpoints ────────────────────────────────────────────

    /**
     * GET /api/admin/submissions
     * List submissions visible to the authenticated admin.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $admin = $request->user();
        $query = Submission::with([
            'user:id,name,student_id,department',
            'formType:id,name,category',
            'department:id,name,code',
            'assignedAdmin:id,name',
        ])->latest('updated_at');

        // Non-super admins only see their department's submissions
        if (!$admin->isSuperAdmin()) {
            $query->where('department_id', $admin->department_id);
        }

        // Filters
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($dept = $request->query('department_id')) {
            $query->where('department_id', $dept);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ref', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%")
                                                    ->orWhere('student_id', 'like', "%{$search}%"));
            });
        }

        return response()->json($query->paginate(25));
    }

    /**
     * PATCH /api/admin/submissions/{ref}/status
     * Transition a submission's status.
     */
    public function updateStatus(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('ref', $ref)
            ->with('formType', 'user')
            ->firstOrFail();

        $admin = $request->user();

        if (!$admin->canAccessSubmission($submission)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $validated = $request->validate([
            'status'            => 'required|in:routed,in_review,action_required,approved,rejected,returned,completed,escalated',
            'comment'           => 'sometimes|string|max:1000',
            'assigned_admin_id' => 'sometimes|nullable|exists:admins,id',
            'return_reason'     => 'required_if:status,returned|string|max:1000',
            'response_deadline' => 'required_if:status,returned|date|after:today',
        ]);

        $oldStatus = $submission->status;

        $submission->update([
            'status'            => $validated['status'],
            'admin_comment'     => $validated['comment'] ?? $submission->admin_comment,
            'assigned_admin_id' => $validated['assigned_admin_id'] ?? $submission->assigned_admin_id,
            'return_reason'     => $validated['return_reason']     ?? $submission->return_reason,
            'response_deadline' => $validated['response_deadline'] ?? $submission->response_deadline,
            'completed_at'      => in_array($validated['status'], ['approved', 'completed']) ? now() : $submission->completed_at,
        ]);

        AuditLog::record(
            $admin, 'status_change',
            "Status changed from {$oldStatus} to {$validated['status']}",
            $ref,
            ['field' => 'status', 'from' => $oldStatus, 'to' => $validated['status']]
        );

        // Dispatch student notification for this status change
        $eventMap = [
            'in_review'       => 'submission_in_review',
            'action_required' => 'action_required',
            'approved'        => 'submission_approved',
            'rejected'        => 'submission_rejected',
            'returned'        => 'submission_returned',
            'completed'       => 'submission_approved',
        ];

        if ($eventType = ($eventMap[$validated['status']] ?? null)) {
            $this->notifier->dispatch($submission->user, $eventType, [
                'ref'          => $ref,
                'title'        => $submission->formType->name,
                'status_label' => ucwords(str_replace('_', ' ', $validated['status'])),
                'admin_comment'=> $validated['comment'] ?? null,
                'deadline'     => isset($validated['response_deadline'])
                                  ? \Carbon\Carbon::parse($validated['response_deadline'])->format('d M Y')
                                  : null,
                'notif_title'  => ucwords(str_replace('_', ' ', $validated['status'])),
                'notif_body'   => "Your submission {$ref} has been updated.",
            ]);
        }

        return response()->json($submission->fresh());
    }

    /**
     * POST /api/admin/submissions/{ref}/comment
     */
    public function addComment(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('ref', $ref)->with('user')->firstOrFail();
        $admin      = $request->user();

        if (!$admin->canAccessSubmission($submission)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $request->validate(['comment' => 'required|string|max:1000']);

        $submission->update(['admin_comment' => $request->comment]);

        AuditLog::record($admin, 'comment', "Added comment on {$ref}", $ref);

        $this->notifier->dispatch($submission->user, 'admin_comment', [
            'ref'          => $ref,
            'title'        => $submission->formType->name,
            'admin_comment'=> $request->comment,
            'notif_title'  => 'New admin comment',
            'notif_body'   => "An admin added a comment to your submission {$ref}.",
        ]);

        return response()->json(['message' => 'Comment added.']);
    }

    // ── Dynamic field validation ───────────────────────────────────

    private function validateFormData(array $data, array $fields): void
    {
        $rules = [];
        foreach ($fields as $field) {
            if (empty($field['key'])) continue;
            $rule = [];
            if (!empty($field['required'])) {
                $rule[] = 'required';
            } else {
                $rule[] = 'nullable';
            }
            $rule[] = match ($field['type'] ?? 'text') {
                'date'     => 'date',
                'select',
                'radio'    => 'string',
                'checkbox' => 'array',
                default    => 'string|max:5000',
            };
            $rules[$field['key']] = implode('|', $rule);
        }

        validator($data, $rules)->validate();
    }
}
