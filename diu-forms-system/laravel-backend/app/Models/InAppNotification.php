<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InAppNotification extends Model
{
    protected $fillable = [
        'notifiable_id', 'notifiable_type',
        'event_type', 'reference', 'title', 'body', 'read', 'read_at',
    ];

    protected $casts = [
        'read'    => 'boolean',
        'read_at' => 'datetime',
    ];

    public function markRead(): void
    {
        if (!$this->read) {
            $this->update(['read' => true, 'read_at' => now()]);
        }
    }
}
