<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Submission;
use App\Models\SubmissionDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubmissionDocumentController extends Controller
{
    // Allowed MIME types for uploads
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const MAX_SIZE_KB = 5120; // 5 MB

    /**
     * POST /api/student/submissions/{ref}/documents
     * Upload one or more documents to a submission.
     * Only allowed while the submission is in draft, submitted, or returned state.
     */
    public function store(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('ref', $ref)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if (!in_array($submission->status, ['draft', 'submitted', 'returned'])) {
            return response()->json(['message' => 'Documents cannot be added to this submission.'], 422);
        }

        $request->validate([
            'files'           => 'required|array|max:5',
            'files.*'         => 'required|file|max:' . self::MAX_SIZE_KB . '|mimetypes:' . implode(',', self::ALLOWED_MIMES),
            'field_key'       => 'sometimes|string|max:60',
        ]);

        $uploaded = [];

        foreach ($request->file('files') as $file) {
            // Store in private disk under submissions/{ref}/
            $path = $file->storeAs(
                'submissions/' . $ref,
                Str::uuid() . '.' . $file->getClientOriginalExtension(),
                'private'
            );

            $doc = SubmissionDocument::create([
                'submission_id' => $submission->id,
                'field_key'     => $request->input('field_key'),
                'original_name' => $file->getClientOriginalName(),
                'stored_path'   => $path,
                'mime_type'     => $file->getMimeType(),
                'size_bytes'    => $file->getSize(),
            ]);

            AuditLog::record(
                $request->user(), 'document',
                "Uploaded \"{$file->getClientOriginalName()}\" to {$ref}",
                $ref
            );

            $uploaded[] = [
                'id'            => $doc->id,
                'original_name' => $doc->original_name,
                'size'          => $doc->size_formatted,
                'mime_type'     => $doc->mime_type,
            ];
        }

        return response()->json(['uploaded' => $uploaded], 201);
    }

    /**
     * GET /api/student/documents/{id}/download
     * Returns a short-lived signed URL (30 min) for the student's own document.
     */
    public function download(Request $request, int $id): JsonResponse
    {
        $doc = SubmissionDocument::with('submission')->findOrFail($id);

        if ($doc->submission->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        return response()->json(['url' => $doc->temporaryUrl()]);
    }

    /**
     * GET /api/admin/submissions/{ref}/documents
     */
    public function adminList(Request $request, string $ref): JsonResponse
    {
        $submission = Submission::where('ref', $ref)->with('documents')->firstOrFail();

        if (!$request->user()->canAccessSubmission($submission)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $docs = $submission->documents->map(fn ($d) => [
            'id'            => $d->id,
            'original_name' => $d->original_name,
            'size'          => $d->size_formatted,
            'mime_type'     => $d->mime_type,
            'field_key'     => $d->field_key,
            'uploaded_at'   => $d->created_at->format('d M Y H:i'),
        ]);

        return response()->json($docs);
    }

    /**
     * GET /api/admin/documents/{id}/download
     */
    public function adminDownload(Request $request, int $id): JsonResponse
    {
        $doc        = SubmissionDocument::with('submission')->findOrFail($id);
        $submission = $doc->submission;

        if (!$request->user()->canAccessSubmission($submission)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        AuditLog::record(
            $request->user(), 'document',
            "Downloaded \"{$doc->original_name}\" from {$submission->ref}",
            $submission->ref
        );

        return response()->json(['url' => $doc->temporaryUrl()]);
    }
}
