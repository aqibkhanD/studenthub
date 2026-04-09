<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubmissionComment extends Model
{
    protected $fillable = [
        'submission_id', 'user_id', 'body',
        'is_internal', 'is_system', 'parent_id',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_system'   => 'boolean',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SubmissionComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SubmissionComment::class, 'parent_id')->orderBy('created_at');
    }
}
