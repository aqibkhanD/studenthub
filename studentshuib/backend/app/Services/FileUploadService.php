<?php

namespace App\Services;

use App\Models\Submission;
use App\Models\SubmissionDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    // Allowed MIME types for student uploads
    const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    const MAX_SIZE_BYTES = 20 * 1024 * 1024; // 20 MB

    /**
     * Store an uploaded file and create a SubmissionDocument record.
     */
    public function store(
        UploadedFile $file,
        Submission $submission,
        User $uploader,
        string $docType = 'student_upload',
        ?string $description = null,
        bool $isPublic = true
    ): SubmissionDocument {
        $this->validate($file);

        // Path: submissions/{submission_id}/{uuid}.{ext}
        $ext      = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $ext;
        $path     = "submissions/{$submission->id}/{$filename}";

        Storage::disk(config('filesystems.default', 'local'))->putFileAs(
            "submissions/{$submission->id}",
            $file,
            $filename
        );

        return SubmissionDocument::create([
            'submission_id' => $submission->id,
            'uploaded_by'   => $uploader->id,
            'file_name'     => $file->getClientOriginalName(),
            'file_path'     => $path,
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
            'document_type' => $docType,
            'description'   => $description,
            'is_public'     => $isPublic,
        ]);
    }

    /**
     * Delete a document file and its DB record.
     */
    public function delete(SubmissionDocument $doc): void
    {
        Storage::disk(config('filesystems.default', 'local'))->delete($doc->file_path);
        $doc->delete();
    }

    private function validate(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_SIZE_BYTES) {
            throw new \InvalidArgumentException('File exceeds 20 MB limit.');
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIMES)) {
            throw new \InvalidArgumentException(
                'File type not allowed. Accepted: PDF, JPG, PNG, WEBP, DOC, DOCX.'
            );
        }
    }
}
