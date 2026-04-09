<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'submission_id', 'channel', 'type', 'title', 'body',
        'phone_number', 'is_read', 'sent_at', 'read_at', 'failed_at',
        'failure_reason', 'created_at',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'sent_at'    => 'datetime',
        'read_at'    => 'datetime',
        'failed_at'  => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
