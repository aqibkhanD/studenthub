<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FormType;
use App\Models\Submission;
use App\Services\FileUploadService;
use App\Services\SubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function __construct(
        private SubmissionService $service,
        private FileUploadService $uploads
    ) {}

    // GET /api/v1/student/submissions
    public function index(Request $request): JsonResponse
    {
        $query = Submission::where('student_id', $request->user()->id)
            ->with(['formType:id,name,category', 'department:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category')) {
            $query->whereHas('formType', fn($q) => $q->where('category', $request->category));
        }

        $submissions = $query->paginate(15);

        return response()->json($submissions);
    }

    // POST /api/v1/student/submissions
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'form_type_id'  => 'required|exists:form_types,id',
            'is_anonymous'  => 'boolean',
            'form_data'     => 'required|array',
            'submit'        => 'boolean', // true = submit now, false = save as draft
        ]);

        $formType = FormType::findOrFail($data['form_type_id']);

        // Validate anonymous submission is allowed
        if (!empty($data['is_anonymous']) && !$formType->allow_anonymous) {
            return response()->json(['message' => 'Anonymous submission is not allowed for this form type.'], 422);
        }

        $submission = $this->service->create(
            user:        $request->user(),
            formType:    $formType,
            formData:    $data['form_data'],
            isAnonymous: $data['is_anonymous'] ?? false,
            submit:      $data['submit'] ?? true,
        );

        return response()->json([
            'message'    => $submission->status === Submission::STATUS_SUBMITTED
                ? 'Submission received. Reference: ' . $submission->reference_no
                : 'Draft saved.',
            'submission' => $this->formatSubmission($submission),
        ], 201);
    }

    // GET /api/v1/student/submissions/{ref}
    public function show(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('reference_no', $ref)
            ->where('student_id', $request->user()->id)
            ->with([
                'formType', 'department',
                'statusHistory' => fn($q) => $q->where('is_visible_to_student', true),
                'documents'     => fn($q) => $q->where('is_public', true),
                'comments'      => fn($q) => $q->where('is_internal', false)->with('user:id,name,role'),
            ])
            ->firstOrFail();

        return response()->json(['submission' => $this->formatSubmission($submission, detailed: true)]);
    }

    // PUT /api/v1/student/submissions/{ref}  — re-submit a returned submission
    public function update(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('reference_no', $ref)
            ->where('student_id', $request->user()->id)
            ->whereIn('status', [Submission::STATUS_RETURNED, Submission::STATUS_DRAFT])
            ->firstOrFail();

        $data = $request->validate([
            'form_data' => 'required|array',
            'submit'    => 'boolean',
        ]);

        $submission = $this->service->resubmit($submission, $data['form_data'], $request->user());

        return response()->json([
            'message'    => 'Re-submitted successfully.',
            'submission' => $this->formatSubmission($submission),
        ]);
    }

    // DELETE /api/v1/student/submissions/{ref}  — cancel a draft
    public function cancel(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('reference_no', $ref)
            ->where('student_id', $request->user()->id)
            ->where('status', Submission::STATUS_DRAFT)
            ->firstOrFail();

        $this->service->cancel($submission, $request->user());

        return response()->json(['message' => 'Draft cancelled.']);
    }

    // GET /api/v1/student/submissions/{ref}/comments
    public function comments(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('reference_no', $ref)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        $comments = $submission->comments()
            ->where('is_internal', false)
            ->with('user:id,name,role')
            ->get();

        return response()->json(['comments' => $comments]);
    }

    // POST /api/v1/student/submissions/{ref}/documents
    public function uploadDocument(Request $request, string $ref): JsonResponse
    {
        $request->validate([
            'document'    => 'required|file|max:20480',
            'description' => 'nullable|string|max:255',
        ]);

        // Only allow upload when submission is in a state where additional
        // documents make sense (draft, returned, action_required).
        $submission = Submission::where('reference_no', $ref)
            ->where('student_id', $request->user()->id)
            ->whereIn('status', [
                Submission::STATUS_DRAFT,
                Submission::STATUS_RETURNED,
                Submission::STATUS_ACTION_REQUIRED,
            ])
            ->firstOrFail();

        try {
            $doc = $this->uploads->store(
                file:        $request->file('document'),
                submission:  $submission,
                uploader:    $request->user(),
                docType:     'student_upload',
                description: $request->input('description'),
                isPublic:    true
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['document' => $doc], 201);
    }

    // POST /api/v1/student/submissions/{ref}/comments
    public function addComment(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('reference_no', $ref)
            ->where('student_id', $request->user()->id)
            ->firstOrFail();

        $data = $request->validate(['body' => 'required|string|max:2000']);

        $comment = $submission->comments()->create([
            'user_id'     => $request->user()->id,
            'body'        => $data['body'],
            'is_internal' => false,
            'is_system'   => false,
        ]);

        return response()->json(['comment' => $comment->load('user:id,name,role')], 201);
    }

    // ----------------------------------------------------------
    private function formatSubmission(Submission $s, bool $detailed = false): array
    {
        $base = [
            'id'           => $s->id,
            'reference_no' => $s->reference_no,
            'status'       => $s->status,
            'form_type'    => $s->formType ? ['id' => $s->formType->id, 'name' => $s->formType->name, 'category' => $s->formType->category] : null,
            'department'   => $s->department ? ['id' => $s->department->id, 'name' => $s->department->name] : null,
            'is_anonymous' => $s->is_anonymous,
            'submitted_at' => $s->submitted_at?->toIso8601String(),
            'sla_deadline' => $s->sla_deadline?->toIso8601String(),
            'sla_breached' => $s->isSlaBreached(),
            'created_at'   => $s->created_at->toIso8601String(),
        ];

        if ($detailed) {
            $base['form_data']      = $s->form_data;
            $base['status_history'] = $s->statusHistory ?? [];
            $base['documents']      = $s->documents ?? [];
            $base['comments']       = $s->comments ?? [];
        }

        return $base;
    }
}
