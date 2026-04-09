<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Submission;
use App\Models\SubmissionStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Management dashboard stats API.
 *
 * Single endpoint: GET /api/admin/dashboard?period=7d|30d|semester
 *
 * Role-scoped automatically:
 *   - super_admin → all departments
 *   - admin       → their department only (filtered by department_id)
 *
 * Response shape:
 *   { period, scope, department, kpi, donut, volume, departments, activity }
 */
class DashboardController extends Controller
{
    private const PERIODS = ['7d', '30d', 'semester'];

    // ──────────────────────────────────────────────────────────────
    // GET /api/admin/dashboard
    // ──────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $period = $request->query('period', 'semester');
        if (!in_array($period, self::PERIODS)) {
            $period = 'semester';
        }

        $user         = $request->user();
        $isSuperAdmin = $user->role === 'super_admin';
        $deptId       = $isSuperAdmin ? null : $user->department_id;

        [$periodStart, $prevStart, $prevEnd] = $this->resolvePeriodBounds($period);

        return response()->json([
            'period'      => $period,
            'scope'       => $isSuperAdmin ? 'all' : 'department',
            'department'  => $isSuperAdmin ? null : $user->department?->name,
            'kpi'         => $this->buildKpis($periodStart, $prevStart, $prevEnd, $deptId),
            'donut'       => $this->buildDonut($deptId),
            'volume'      => $this->buildVolume($period, $periodStart, $deptId),
            'departments' => $this->buildDepartments($periodStart, $deptId),
            'activity'    => $this->buildActivity($deptId),
        ]);
    }

    // ── Lightweight stats widget (used by topbar badge counts) ────

    public function stats(Request $request): JsonResponse
    {
        $user   = $request->user();
        $deptId = $user->role === 'super_admin' ? null : $user->department_id;

        $base = Submission::when($deptId, fn($q) => $q->where('department_id', $deptId));

        return response()->json([
            'pending'   => (clone $base)->whereIn('status', ['submitted', 'routed'])->count(),
            'in_review' => (clone $base)->where('status', 'in_review')->count(),
            'overdue'   => (clone $base)->overdue()->count(),
            'escalated' => (clone $base)->where('status', 'escalated')->count(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // KPI block
    // ──────────────────────────────────────────────────────────────

    private function buildKpis(Carbon $periodStart, Carbon $prevStart, Carbon $prevEnd, ?int $deptId): array
    {
        $base = fn() => Submission::when($deptId, fn($q) => $q->where('department_id', $deptId));

        // Current period counts
        $totalCurrent     = (clone $base())->where('submitted_at', '>=', $periodStart)->count();
        $completedCurrent = (clone $base())
            ->whereIn('status', ['completed', 'approved'])
            ->where('resolved_at', '>=', $periodStart)
            ->count();

        // Previous period counts (for trend calculation)
        $totalPrev     = (clone $base())->whereBetween('submitted_at', [$prevStart, $prevEnd])->count();
        $completedPrev = (clone $base())
            ->whereIn('status', ['completed', 'approved'])
            ->whereBetween('resolved_at', [$prevStart, $prevEnd])
            ->count();

        // Live snapshot (not period-bound — these are "right now" figures)
        $pending   = (clone $base())->whereIn('status', ['submitted', 'routed', 'in_review', 'action_required'])->count();
        $overdue   = (clone $base())->overdue()->count();
        $escalated = (clone $base())->where('status', 'escalated')->count();

        // Average resolution time in hours, converted to days for display
        $avgHours = (clone $base())
            ->whereIn('status', ['completed', 'approved'])
            ->where('submitted_at', '>=', $periodStart)
            ->whereNotNull('resolved_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, submitted_at, resolved_at)) as avg_hours')
            ->value('avg_hours');

        return [
            'total'               => $totalCurrent,
            'total_trend'         => $this->trend($totalCurrent, $totalPrev),
            'completed'           => $completedCurrent,
            'completed_trend'     => $this->trend($completedCurrent, $completedPrev),
            'pending'             => $pending,
            'overdue'             => $overdue,
            'escalated'           => $escalated,
            'avg_resolution_days' => $avgHours ? round($avgHours / 24, 1) : null,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Donut — current snapshot grouped by status
    // ──────────────────────────────────────────────────────────────

    private function buildDonut(?int $deptId): array
    {
        $rows = Submission::when($deptId, fn($q) => $q->where('department_id', $deptId))
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Fixed order with display labels and brand colours
        $statusMap = [
            'submitted'       => ['label' => 'Pending',          'color' => '#D97706'],
            'routed'          => ['label' => 'Routed',           'color' => '#F59E0B'],
            'in_review'       => ['label' => 'In Review',        'color' => '#1D6FA4'],
            'action_required' => ['label' => 'Action Required',  'color' => '#7C3AED'],
            'escalated'       => ['label' => 'Escalated',        'color' => '#B8922A'],
            'approved'        => ['label' => 'Approved',         'color' => '#2563EB'],
            'completed'       => ['label' => 'Completed',        'color' => '#1A7A4A'],
            'rejected'        => ['label' => 'Rejected',         'color' => '#C0392B'],
            'returned'        => ['label' => 'Returned',         'color' => '#64748B'],
        ];

        $labels = $values = $colors = [];

        foreach ($statusMap as $key => $meta) {
            $count = $rows[$key] ?? 0;
            if ($count === 0) continue; // omit empty slices
            $labels[] = $meta['label'];
            $values[] = $count;
            $colors[] = $meta['color'];
        }

        return compact('labels', 'values', 'colors');
    }

    // ──────────────────────────────────────────────────────────────
    // Volume — submissions per time bucket
    // ──────────────────────────────────────────────────────────────

    private function buildVolume(string $period, Carbon $periodStart, ?int $deptId): array
    {
        $base = Submission::when($deptId, fn($q) => $q->where('department_id', $deptId))
            ->where('submitted_at', '>=', $periodStart)
            ->whereNotNull('submitted_at');

        if ($period === '7d') {
            // One point per day
            $rows = (clone $base)
                ->selectRaw('DATE(submitted_at) as bucket, COUNT(*) as count')
                ->groupByRaw('DATE(submitted_at)')
                ->orderBy('bucket')
                ->get();

            $dataByDate = $rows->pluck('count', 'bucket')->toArray();
            $labels = $values = [];
            $cursor = $periodStart->copy();

            for ($i = 0; $i < 7; $i++) {
                $key      = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('D');          // "Mon", "Tue"…
                $values[] = (int) ($dataByDate[$key] ?? 0);
                $cursor->addDay();
            }

        } elseif ($period === '30d') {
            // Pair consecutive days to reduce to ~15 points — keeps the chart readable
            $rows = (clone $base)
                ->selectRaw('DATE(submitted_at) as bucket, COUNT(*) as count')
                ->groupByRaw('DATE(submitted_at)')
                ->orderBy('bucket')
                ->get();

            $dataByDate = $rows->pluck('count', 'bucket')->toArray();
            $labels = $values = [];
            $cursor = $periodStart->copy();

            for ($i = 0; $i < 30; $i += 2) {
                $key1 = $cursor->format('Y-m-d');
                $key2 = $cursor->copy()->addDay()->format('Y-m-d');
                $labels[] = $cursor->format('M d');
                $values[] = (int) (($dataByDate[$key1] ?? 0) + ($dataByDate[$key2] ?? 0));
                $cursor->addDays(2);
            }

        } else {
            // Semester — one point per month
            $rows = (clone $base)
                ->selectRaw("DATE_FORMAT(submitted_at, '%Y-%m') as bucket, COUNT(*) as count")
                ->groupByRaw("DATE_FORMAT(submitted_at, '%Y-%m')")
                ->orderBy('bucket')
                ->get();

            $labels = $rows->map(fn($r) => Carbon::parse($r->bucket . '-01')->format('M Y'))->toArray();
            $values = $rows->pluck('count')->map(fn($v) => (int) $v)->toArray();
        }

        return compact('labels', 'values');
    }

    // ──────────────────────────────────────────────────────────────
    // Department performance table
    // ──────────────────────────────────────────────────────────────

    private function buildDepartments(Carbon $periodStart, ?int $deptId): array
    {
        $departments = Department::where('is_active', true)
            ->when($deptId, fn($q) => $q->where('id', $deptId))
            ->orderBy('name')
            ->get();

        return $departments->map(function (Department $dept) use ($periodStart) {

            $base = fn() => Submission::where('department_id', $dept->id);

            $pending  = (clone $base())->whereIn('status', ['submitted', 'routed'])->count();
            $inReview = (clone $base())->where('status', 'in_review')->count();
            $overdue  = (clone $base())->overdue()->count();

            // Avg resolution for this period
            $avgHours = (clone $base())
                ->whereIn('status', ['completed', 'approved'])
                ->where('submitted_at', '>=', $periodStart)
                ->whereNotNull('resolved_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, submitted_at, resolved_at)) as avg_hours')
                ->value('avg_hours');

            $avgDisplay = $avgHours ? round($avgHours / 24, 1) . 'd' : '—';

            // SLA compliance: % resolved before deadline within the period
            $resolved = (clone $base())
                ->whereIn('status', ['completed', 'approved', 'rejected'])
                ->where('submitted_at', '>=', $periodStart)
                ->whereNotNull('resolved_at')
                ->count();

            $onTime = $resolved > 0
                ? (clone $base())
                    ->whereIn('status', ['completed', 'approved', 'rejected'])
                    ->where('submitted_at', '>=', $periodStart)
                    ->whereNotNull('resolved_at')
                    ->whereRaw('resolved_at <= sla_deadline')
                    ->count()
                : 0;

            return [
                'id'             => $dept->id,
                'name'           => $dept->name,
                'code'           => $dept->code,
                'pending'        => $pending,
                'in_review'      => $inReview,
                'overdue'        => $overdue,
                'avg_resolution' => $avgDisplay,
                'sla_compliance' => $resolved > 0 ? (int) round($onTime / $resolved * 100) : null,
            ];
        })->toArray();
    }

    // ──────────────────────────────────────────────────────────────
    // Recent activity feed (last 12 visible transitions)
    // ──────────────────────────────────────────────────────────────

    private function buildActivity(?int $deptId): array
    {
        $rows = SubmissionStatusHistory::with(['submission.department', 'changedBy'])
            ->when(
                $deptId,
                fn($q) => $q->whereHas('submission', fn($sq) => $sq->where('department_id', $deptId))
            )
            ->where('visible_to_student', true)
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        $statusColors = [
            'approved'        => '#1A7A4A',
            'completed'       => '#1A7A4A',
            'rejected'        => '#C0392B',
            'in_review'       => '#1D6FA4',
            'escalated'       => '#B8922A',
            'returned'        => '#64748B',
            'submitted'       => '#2563EB',
            'routed'          => '#2563EB',
            'action_required' => '#7C3AED',
        ];

        return $rows->map(function ($row) use ($statusColors) {
            $dept   = $row->submission?->department?->name ?? 'Unknown Dept';
            $status = ucwords(str_replace('_', ' ', $row->new_status));
            $ref    = $row->submission?->reference_number ?? '—';

            return [
                'dot'    => $statusColors[$row->new_status] ?? '#64748B',
                'action' => "<strong>{$dept}</strong> — {$status}",
                'actor'  => $row->changedBy?->name ?? 'System',
                'ref'    => $ref,
                'time'   => $row->created_at->diffForHumans(),
            ];
        })->toArray();
    }

    // ──────────────────────────────────────────────────────────────
    // Period bounds helper
    // Returns [currentStart, prevPeriodStart, prevPeriodEnd]
    // ──────────────────────────────────────────────────────────────

    private function resolvePeriodBounds(string $period): array
    {
        $now = Carbon::now();

        if ($period === '7d') {
            return [
                $now->copy()->subDays(7)->startOfDay(),
                $now->copy()->subDays(14)->startOfDay(),
                $now->copy()->subDays(7)->endOfDay(),
            ];
        }

        if ($period === '30d') {
            return [
                $now->copy()->subDays(30)->startOfDay(),
                $now->copy()->subDays(60)->startOfDay(),
                $now->copy()->subDays(30)->endOfDay(),
            ];
        }

        // Semester — Spring: Jan–Jun, Fall: Jul–Dec
        $year  = $now->year;
        $month = $now->month;

        if ($month <= 6) {
            return [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year - 1, 7, 1)->startOfDay(),
                Carbon::create($year - 1, 12, 31)->endOfDay(),
            ];
        }

        return [
            Carbon::create($year, 7, 1)->startOfDay(),
            Carbon::create($year, 1, 1)->startOfDay(),
            Carbon::create($year, 6, 30)->endOfDay(),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Trend string: "+12%" / "-5%" / "New" / "—"
    // ──────────────────────────────────────────────────────────────

    private function trend(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? 'New' : '—';
        }

        $pct = (int) round(($current - $previous) / $previous * 100);

        if ($pct === 0) return '0%';

        return ($pct > 0 ? '+' : '') . $pct . '%';
    }
}
