<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;  // only created_at, set manually

    protected $table = 'audit_log';

    protected $fillable = [
        'actor_id', 'actor_type', 'actor_name', 'actor_role',
        'action', 'reference', 'details', 'diff',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'diff'       => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Convenience factory method — called from AuditLogObserver and controllers.
     */
    public static function record(
        Model  $actor,
        string $action,
        string $details,
        ?string $reference = null,
        ?array  $diff = null
    ): self {
        return self::create([
            'actor_id'   => $actor->getKey(),
            'actor_type' => get_class($actor),
            'actor_name' => $actor->name,
            'actor_role' => $actor instanceof Admin ? $actor->role : 'student',
            'action'     => $action,
            'reference'  => $reference,
            'details'    => $details,
            'diff'       => $diff,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
