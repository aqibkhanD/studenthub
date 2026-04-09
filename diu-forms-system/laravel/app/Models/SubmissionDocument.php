<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionDocument extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'submission_id', 'uploaded_by', 'file_name', 'file_path',
        'file_size', 'mime_type', 'document_type', 'description',
        'is_public', 'created_at',
    ];

    protected $casts = [
        'is_public'  => 'boolean',
        'created_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return asset('storage/' . $this->file_path);
    }

    public function humanSize(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024)        return "{$bytes} B";
        if ($bytes < 1048576)     return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
