<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SubmissionDocument extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'submission_id', 'uploaded_by', 'file_name', 'file_path',
        'file_size', 'mime_type', 'document_type', 'description', 'is_public',
    ];

    protected $casts = [
        'is_public'  => 'boolean',
        'created_at' => 'datetime',
    ];

    protected $appends = ['url', 'size_human', 'original_name', 'source'];

    public function getUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    public function getSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024)       return "{$bytes} B";
        if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    /**
     * Alias for file_name — frontend expects `original_name`.
     */
    public function getOriginalNameAttribute(): string
    {
        return $this->file_name;
    }

    /**
     * Simplified source tag — 'admin' or 'student' — for frontend display.
     */
    public function getSourceAttribute(): string
    {
        return $this->document_type === 'admin_upload' ? 'admin' : 'student';
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
