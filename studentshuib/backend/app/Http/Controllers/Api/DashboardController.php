<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Department;
use App\Models\Submission;
use App\Models\SubmissionStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // GET /api/v1/admin/dashboard?period=week|month|semester
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $deptId = ($user->role === 'admin') ? $user->department_id : null;

        $period = $request->input('period', 'month');
        if (!in_array($period, ['week', 'month', 'semester'], true)) {
            $period = 'month';
        }

        [$periodStart, $periodEnd, $periodLabel] = $this->resolvePeriod($period);
        $lengthSec     = max(1, $periodEnd->diffInSeconds($periodStart));
        $previousStart = $periodStart->copy()->subSeconds($lengthSec);
        $previousEnd   = $periodStart->copy();

        // Base submissions query (with dept scoping for dept-scoped admins)
        $base = Submission::when($deptId, fn($q) => $q->where('department_id', $deptId));

        // ── Period-based metrics ──────────────────────────────────────────
        $totalSubmittedCurrent  = (clone $base)
            ->whereBetween('submitted_at', [$periodStart, $periodEnd])
            ->whereNotNull('submitted_at')->count();
        $totalSubmittedPrevious = (clone $base)
            ->whereBetween('submitted_at', [$previousStart, $previousEnd])
            ->whereNotNull('submitted_at')->count();

        $completedCurrent  = (clone $base)
            ->whereIn('status', ['approved', 'completed'])
            ->whereBetween('resolved_at', [$periodStart, $periodEnd])->count();
        $completedPrevious = (clone $base)
            ->whereIn('status', ['approved', 'completed'])
            ->whereBetween('resolved_at', [$previousStart, $previousEnd])->count();

        // Avg resolution time in DAYS for submissions resolved in the period
        $avgResolutionDays = (clone $base)
            ->whereNotNull('resolved_at')
            ->whereNotNull('submitted_at')
            ->whereBetween('resolved_at', [$periodStart, $periodEnd])
            ->selectRaw("avg(extract(epoch from (resolved_at - submitted_at)) / 86400) as avg_days")
            ->value('avg_days');

        // ── Snapshot metrics (current state, period-independent) ─────────
        $statusCounts = (clone $base)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $pendingReview = (int)(($statusCounts['submitted'] ?? 0) + ($statusCounts['routed'] ?? 0));
        $escalated     = (int)($statusCounts['escalated'] ?? 0);
        $totalActive   = (int)(($statusCounts['submitted'] ?? 0)
            + ($statusCounts['routed'] ?? 0)
            + ($statusCounts['in_review'] ?? 0)
            + ($statusCounts['action_required'] ?? 0)
            + ($statusCounts['escalated'] ?? 0));
        $totalSnapshot = (int)$statusCounts->sum();

        $overdue = (clone $base)
            ->whereNotIn('status', ['approved', 'rejected', 'completed', 'cancelled'])
            ->where('sla_deadline', '<', now())
            ->count();

        $unassigned = (clone $base)
            ->whereIn('status', ['submitted', 'routed'])
            ->whereNull('assigned_to')
            ->count();

        // ── Submission volume (line chart data) ───────────────────────────
        $volume     = $this->computeVolume($base, $period, $periodStart, $periodEnd);
        $volumePeak = (int)($volume->max('count') ?: 0);

        // ── Department performance (only for non-dept-scoped roles) ──────
        $departments = $deptId === null
            ? Department::where('is_active', true)
                ->withCount(['submissions as period_total' => fn($q) => $q->whereBetween('submitted_at', [$periodStart, $periodEnd])])
                ->withCount(['submissions as open_count'   => fn($q) => $q->whereIn('status', ['submitted','routed','in_review','action_required'])])
                ->withCount(['submissions as breached_count' => fn($q) => $q->whereNotIn('status', ['approved','rejected','completed','cancelled'])->where('sla_deadline','<',now())])
                ->orderBy('name')
                ->get()
                ->map(fn($d) => [
                    'id'             => $d->id,
                    'name'           => $d->name,
                    'period_total'   => (int)$d->period_total,
                    'open_count'     => (int)$d->open_count,
                    'breached_count' => (int)$d->breached_count,
                ])
            : null;

        // ── Recent activity (last 10 status changes) ──────────────────────
        $recentActivity = SubmissionStatusHistory::with([
                'submission:id,reference_no,form_type_id,department_id',
                'submission.formType:id,name',
                'submission.department:id,name',
                'changedBy:id,name,role',
            ])
            ->when($deptId, fn($q) => $q->whereHas('submission', fn($q2) => $q2->where('department_id', $deptId)))
            ->orderByDesc('changed_at')
            ->limit(10)
            ->get()
            ->map(fn($h) => [
                'id'           => $h->id,
                'reference_no' => $h->submission?->reference_no,
                'form_type'    => $h->submission?->formType?->name,
                'department'   => $h->submission?->department?->name,
                'from_status'  => $h->from_status,
                'to_status'    => $h->to_status,
                'comment'      => $h->comment,
                'changed_at'   => $h->changed_at?->toIso8601String(),
                'changed_by'   => $h->changedBy?->name,
            ]);

        // ── Existing fields kept for backwards compat ─────────────────────
        $topFormTypes = (clone $base)
            ->where('submitted_at', '>=', now()->subDays(30))
            ->join('form_types', 'submissions.form_type_id', '=', 'form_types.id')
            ->selectRaw('form_types.name, count(*) as count')
            ->groupBy('form_types.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $recentByDay = (clone $base)
            ->where('submitted_at', '>=', now()->subDays(7))
            ->selectRaw("date_trunc('day', submitted_at) as day, count(*) as count")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn($r) => ['date' => $r->day, 'count' => (int)$r->count]);

        return response()->json([
            // Period meta
            'period'        => $period,
            'period_label'  => $periodLabel,
            'period_start'  => $periodStart->toDateString(),
            'period_end'    => $periodEnd->toDateString(),

            // 6 stat cards
            'total_submitted'           => $totalSubmittedCurrent,
            'total_submitted_delta_pct' => $this->deltaPct($totalSubmittedCurrent, $totalSubmittedPrevious),
            'completed'                 => $completedCurrent,
            'completed_delta_pct'       => $this->deltaPct($completedCurrent, $completedPrevious),
            'pending_review'            => $pendingReview,
            'overdue'                   => $overdue,
            'escalated'                 => $escalated,
            'avg_resolution_days'       => $avgResolutionDays !== null ? round((float)$avgResolutionDays, 1) : null,

            // Status breakdown
            'status_counts'  => $statusCounts,
            'total_active'   => $totalActive,
            'total_snapshot' => $totalSnapshot,

            // Submission volume (line chart)
            'submission_volume' => $volume,
            'volume_peak'       => $volumePeak,

            // Department performance + recent activity
            'departments'     => $departments,
            'recent_activity' => $recentActivity,

            // Existing fields (legacy callers)
            'sla_breached'   => $overdue,
            'unassigned'     => $unassigned,
            'total_open'     => $totalActive,
            'recent_by_day'  => $recentByDay,
            'top_form_types' => $topFormTypes,
        ]);
    }

    // ----------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    private function resolvePeriod(string $period): array
    {
        $now = now();
        if ($period === 'week') {
            return [$now->copy()->subDays(7), $now, 'Last 7 days'];
        }
        if ($period === 'month') {
            return [$now->copy()->subDays(30), $now, 'Last 30 days'];
        }
        // Semester — read from app_settings; cap end at "now" for the chart
        $semester = AppSetting::semester();
        $start = Carbon::parse($semester['start'])->startOfDay();
        $end   = Carbon::parse($semester['end'])->endOfDay();
        if ($end->isFuture()) $end = $now;
        return [$start, $end, $semester['label']];
    }

    private function deltaPct(int $current, int $previous): ?float
    {
        if ($previous === 0) return null;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Bucket submissions into daily (week/month) or monthly (semester) data
     * points. Result is an array of { label, count, date } ready for the line chart.
     */
    private function computeVolume($base, string $period, Carbon $start, Carbon $end)
    {
        if ($period === 'semester') {
            return (clone $base)
                ->whereBetween('submitted_at', [$start, $end])
                ->whereNotNull('submitted_at')
                ->selectRaw("date_trunc('month', submitted_at) as bucket, count(*) as count")
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get()
                ->map(fn($r) => [
                    'label' => Carbon::parse($r->bucket)->format('M'),
                    'count' => (int)$r->count,
                    'date'  => Carbon::parse($r->bucket)->toDateString(),
                ]);
        }

        return (clone $base)
            ->whereBetween('submitted_at', [$start, $end])
            ->whereNotNull('submitted_at')
            ->selectRaw("date_trunc('day', submitted_at) as bucket, count(*) as count")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get()
            ->map(fn($r) => [
                'label' => Carbon::parse($r->bucket)->format('M d'),
                'count' => (int)$r->count,
                'date'  => Carbon::parse($r->bucket)->toDateString(),
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
