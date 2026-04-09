<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AuditLogController extends Controller
{
    /**
     * GET /api/admin/audit-log
     * Filterable, paginated audit log for admin dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        // Non-super admins only see events in their own department's submissions
        if (!$request->user()->isSuperAdmin()) {
            // Filter to events where the reference belongs to their dept
            $deptRefs = \App\Models\Submission::where('department_id', $request->user()->department_id)
                ->pluck('ref');
            $query->where(function ($q) use ($deptRefs) {
                $q->whereIn('reference', $deptRefs)
                  ->orWhere('actor_id',   $request->user()->id)
                     ->where('actor_type', \App\Models\Admin::class);
            });
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference',  'like', "%{$search}%")
                  ->orWhere('details',  'like', "%{$search}%")
                  ->orWhere('actor_name','like', "%{$search}%");
            });
        }

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($role = $request->query('actor_role')) {
            $query->where('actor_role', $role);
        }

        if ($from = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return response()->json($query->paginate(50));
    }

    /**
     * GET /api/admin/audit-log/{id}
     */
    public function show(int $id): JsonResponse
    {
        return response()->json(AuditLog::findOrFail($id));
    }

    /**
     * GET /api/admin/audit-log/export
     * Download filtered results as CSV.
     */
    public function export(Request $request)
    {
        // Reuse same query logic as index but without pagination
        $entries = AuditLog::orderByDesc('created_at')
            ->when($request->query('action'),      fn($q, $v) => $q->where('action', $v))
            ->when($request->query('actor_role'),  fn($q, $v) => $q->where('actor_role', $v))
            ->when($request->query('date_from'),   fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('date_to'),     fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->limit(5000)
            ->get();

        $csv = "ID,Timestamp,Actor,Role,Action,Reference,Details,IP\n";
        foreach ($entries as $e) {
            $csv .= implode(',', [
                $e->id,
                '"' . $e->created_at->format('Y-m-d H:i:s') . '"',
                '"' . $e->actor_name . '"',
                $e->actor_role,
                $e->action,
                $e->reference ?? '',
                '"' . str_replace('"', '""', $e->details) . '"',
                $e->ip_address ?? '',
            ]) . "\n";
        }

        return Response::make($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-log-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
