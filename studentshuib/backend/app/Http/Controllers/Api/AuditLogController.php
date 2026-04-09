<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    // GET /api/v1/super/audit-logs
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::with('user:id,name,role')
            ->when($request->filled('user_id'),        fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('action'),         fn($q) => $q->where('action', 'ilike', '%' . $request->action . '%'))
            ->when($request->filled('auditable_type'), fn($q) => $q->where('auditable_type', $request->auditable_type))
            ->when($request->filled('auditable_id'),   fn($q) => $q->where('auditable_id', $request->auditable_id))
            ->when($request->filled('date_from'),      fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),        fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($logs);
    }

    // GET /api/v1/super/audit-logs/{id}
    public function show(int $id): JsonResponse
    {
        return response()->json(['log' => AuditLog::with('user:id,name,role')->findOrFail($id)]);
    }
}
