<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Submission;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    // GET /api/v1/admin/reports/analytics
    // Returns a PDF analytics snapshot. Dept admins are scoped to their department.
    // Query params: ?days=7|30|90 (default 30)
    public function analytics(Request $request): \Illuminate\Http\Response
    {
        $days   = (int) $request->input('days', 30);
        $days   = in_array($days, [7, 30, 90]) ? $days : 30;
        $from   = now()->subDays($days);

        $user   = $request->user();
        $deptId = ($user->role === 'admin' && $user->department_id) ? $user->department_id : null;

        // ---- Overview stats ----
        $baseQ = Submission::when($deptId, fn($q) => $q->where('department_id', $deptId));

        $totalSubmissions    = (clone $baseQ)->count();
        $submissionsInPeriod = (clone $baseQ)->where('submitted_at', '>=', $from)->count();
        $resolvedInPeriod    = (clone $baseQ)
            ->whereIn('status', ['approved', 'completed', 'rejected'])
            ->where('updated_at', '>=', $from)
            ->count();
        $slaBreachedCount    = (clone $baseQ)
            ->where('sla_deadline', '<', now())
            ->whereNotIn('status', ['approved', 'rejected', 'completed', 'cancelled'])
            ->count();
        $pendingCount        = (clone $baseQ)
            ->whereIn('status', ['submitted', 'routed', 'in_review', 'action_required', 'escalated'])
            ->count();

        // Avg resolution time (hours)
        $avgResolution = (clone $baseQ)
            ->whereIn('status', ['approved', 'completed'])
            ->whereNotNull('submitted_at')
            ->whereNotNull('resolved_at')
            ->selectRaw("AVG(EXTRACT(EPOCH FROM (resolved_at - submitted_at)) / 3600) as avg_hours")
            ->value('avg_hours');

        $slaCompliancePct = $totalSubmissions > 0
            ? round((($totalSubmissions - $slaBreachedCount) / $totalSubmissions) * 100)
            : 100;

        // ---- Department breakdown ----
        $departments = Department::when($deptId, fn($q) => $q->where('id', $deptId))
            ->with(['submissions' => fn($q) => $q->where('submitted_at', '>=', $from)])
            ->withCount([
                'submissions as total_submissions' => fn($q) => $q->where('submitted_at', '>=', $from),
                'submissions as open_submissions'  => fn($q) => $q->whereNotIn('status', ['approved', 'rejected', 'completed', 'cancelled']),
                'submissions as sla_breached'      => fn($q) => $q
                    ->where('sla_deadline', '<', now())
                    ->whereNotIn('status', ['approved', 'rejected', 'completed', 'cancelled']),
            ])
            ->orderByDesc('total_submissions')
            ->get();

        // ---- Status breakdown ----
        $statusBreakdown = (clone $baseQ)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->orderByDesc('count')
            ->pluck('count', 'status')
            ->toArray();

        // ---- Top 5 most overdue ----
        $overdue = Submission::with(['formType:id,name', 'department:id,name', 'student:id,name'])
            ->when($deptId, fn($q) => $q->where('department_id', $deptId))
            ->where('sla_deadline', '<', now())
            ->whereNotIn('status', ['approved', 'rejected', 'completed', 'cancelled'])
            ->orderBy('sla_deadline')
            ->limit(10)
            ->get()
            ->map(fn($s) => [
                'reference_no'  => $s->reference_no,
                'form_type'     => $s->formType?->name,
                'department'    => $s->department?->name,
                'student'       => $s->is_anonymous ? 'Anonymous' : $s->student?->name,
                'hours_overdue' => (int) now()->diffInHours($s->sla_deadline),
                'status'        => $s->status,
            ]);

        $data = compact(
            'days', 'from', 'totalSubmissions', 'submissionsInPeriod', 'resolvedInPeriod',
            'slaBreachedCount', 'pendingCount', 'avgResolution', 'slaCompliancePct',
            'departments', 'statusBreakdown', 'overdue'
        );

        $data['generatedAt'] = now()->format('d M Y, H:i');
        $data['reportScope'] = $deptId
            ? ($departments->first()?->name ?? 'Department')
            : 'All Departments';

        $pdf = Pdf::loadView('reports.analytics', $data)
            ->setPaper('a4', 'portrait');

        $filename = 'analytics_report_' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }
}
