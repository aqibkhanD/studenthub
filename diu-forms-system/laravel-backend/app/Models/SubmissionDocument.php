<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SubmissionDocument extends Model
{
    protected $fillable = [
        'submission_id', 'field_key', 'original_name',
        'stored_path', 'mime_type', 'size_bytes',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * Generate a temporary signed URL valid for 30 minutes.
     * Files are stored on the private disk and never publicly accessible.
     */
    public function temporaryUrl(int $minutes = 30): string
    {
        return Storage::disk('private')->temporaryUrl(
            $this->stored_path,
            now()->addMinutes($minutes)
        );
    }

    public function getSizeFormattedAttribute(): string
    {
        $bytes = $this->size_bytes ?? 0;
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1)    . ' KB';
        return $bytes . ' B';
    }
}
