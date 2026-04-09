<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(
        ?int $userId,
        string $action,
        string $auditableType,
        int $auditableId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        AuditLog::create([
            'user_id'        => $userId,
            'action'         => $action,
            'auditable_type' => $auditableType,
            'auditable_id'   => $auditableId,
            'old_values'     => $oldValues,
            'new_values'     => $newValues,
            'ip_address'     => $ip ?? Request::ip(),
            'user_agent'     => $userAgent ?? Request::userAgent(),
            'created_at'     => now(),
        ]);
    }
}
