<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // GET /api/v1/admin/dashboard
    public function index(Request $request): JsonResponse
    {
        $user    = $request->user();
        $deptId  = ($user->role === 'admin') ? $user->department_id : null;

        $base = Submission::when($deptId, fn($q) => $q->where('department_id', $deptId));

        // Counts by status
        $statusCounts = (clone $base)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // SLA breached (open submissions past deadline)
        $slaBreached = (clone $base)
            ->whereNotIn('status', ['approved','rejected','completed','cancelled'])
            ->where('sla_deadline', '<', now())
            ->count();

        // Unassigned open submissions
        $unassigned = (clone $base)
            ->whereIn('status', ['submitted','routed'])
            ->whereNull('assigned_to')
            ->count();

        // Recent submissions (last 7 days)
        $recentByDay = (clone $base)
            ->where('submitted_at', '>=', now()->subDays(7))
            ->selectRaw("date_trunc('day', submitted_at) as day, count(*) as count")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn($r) => ['date' => $r->day, 'count' => $r->count]);

        // Top form types (last 30 days)
        $topFormTypes = (clone $base)
            ->where('submitted_at', '>=', now()->subDays(30))
            ->join('form_types', 'submissions.form_type_id', '=', 'form_types.id')
            ->selectRaw('form_types.name, count(*) as count')
            ->groupBy('form_types.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return response()->json([
            'status_counts'  => $statusCounts,
            'sla_breached'   => $slaBreached,
            'unassigned'     => $unassigned,
            'recent_by_day'  => $recentByDay,
            'top_form_types' => $topFormTypes,
            'total_open'     => ($statusCounts['submitted'] ?? 0) + ($statusCounts['routed'] ?? 0) + ($statusCounts['in_review'] ?? 0),
        ]);
    }

    // GET /api/v1/super/analytics/overview
    public function analyticsOverview(Request $request): JsonResponse
    {
        $period = $request->integer('days', 30);
        $from   = now()->subDays($period);

        $totalStudents      = User::where('role', 'student')->count();
        $totalSubmissions   = Submission::whereNotNull('submitted_at')->count();
        $submissionsInPeriod = Submission::where('submitted_at', '>=', $from)->count();
        $resolvedInPeriod   = Submission::whereIn('status', ['approved','rejected','completed'])
            ->where('resolved_at', '>=', $from)->count();

        // Average resolution time (hours)
        $avgResolution = Submission::whereNotNull('resolved_at')
            ->whereNotNull('submitted_at')
            ->where('resolved_at', '>=', $from)
            ->selectRaw("avg(extract(epoch from (resolved_at - submitted_at)) / 3600) as avg_hours")
            ->value('avg_hours');

        // SLA compliance rate
        $totalResolved = Submission::whereIn('status', ['approved','completed'])
            ->where('resolved_at', '>=', $from)->count();
        $onTime = Submission::whereIn('status', ['approved','completed'])
            ->where('resolved_at', '>=', $from)
            ->whereColumn('resolved_at', '<=', 'sla_deadline')
            ->count();
        $slaCompliance = $totalResolved > 0 ? round(($onTime / $totalResolved) * 100, 1) : 100;

        return response()->json([
            'period_days'          => $period,
            'total_students'       => $totalStudents,
            'total_submissions'    => $totalSubmissions,
            'submissions_in_period'=> $submissionsInPeriod,
            'resolved_in_period'   => $resolvedInPeriod,
            'avg_resolution_hours' => round($avgResolution ?? 0, 1),
            'sla_compliance_pct'   => $slaCompliance,
        ]);
    }

    // GET /api/v1/super/analytics/sla
    public function slaReport(Request $request): JsonResponse
    {
        $breachedByDept = Submission::join('departments', 'submissions.department_id', '=', 'departments.id')
            ->whereNotIn('submissions.status', ['approved','rejected','completed','cancelled'])
            ->where('submissions.sla_deadline', '<', now())
            ->selectRaw('departments.name as department, count(*) as breached_count')
            ->groupBy('departments.name')
            ->orderByDesc('breached_count')
            ->get();

        $overdueList = Submission::with(['formType:id,name', 'student:id,name,student_id', 'department:id,name', 'assignedTo:id,name'])
            ->whereNotIn('status', ['approved','rejected','completed','cancelled'])
            ->where('sla_deadline', '<', now())
            ->orderBy('sla_deadline')
            ->limit(50)
            ->get()
            ->map(fn($s) => [
                'reference_no'    => $s->reference_no,
                'form_type'       => $s->formType?->name,
                'student'         => $s->is_anonymous ? 'Anonymous' : $s->student?->name,
                'department'      => $s->department?->name,
                'assigned_to'     => $s->assignedTo?->name,
                'status'          => $s->status,
                'sla_deadline'    => $s->sla_deadline?->toIso8601String(),
                'hours_overdue'   => round(now()->diffInHours($s->sla_deadline, false) * -1, 1),
            ]);

        return response()->json([
            'breached_by_department' => $breachedByDept,
            'overdue_submissions'    => $overdueList,
        ]);
    }

    // GET /api/v1/super/analytics/departments
    public function departmentReport(Request $request): JsonResponse
    {
        $period = $request->integer('days', 30);
        $from   = now()->subDays($period);

        $stats = Department::where('is_active', true)
            ->withCount(['submissions as total_submissions' => fn($q) => $q->where('submitted_at', '>=', $from)])
            ->withCount(['submissions as open_submissions'  => fn($q) => $q->whereIn('status', ['submitted','routed','in_review','action_required'])])
            ->withCount(['submissions as sla_breached'       => fn($q) => $q->whereNotIn('status', ['approved','rejected','completed','cancelled'])->where('sla_deadline', '<', now())])
            ->get()
            ->map(fn($d) => [
                'id'                => $d->id,
                'name'              => $d->name,
                'total_submissions' => $d->total_submissions,
                'open_submissions'  => $d->open_submissions,
                'sla_breached'      => $d->sla_breached,
            ]);

        return response()->json(['departments' => $stats, 'period_days' => $period]);
    }
}
