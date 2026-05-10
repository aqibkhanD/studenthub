'use client';
import { useState, useMemo } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { adminApi } from '@/lib/api';
import { Card, CardBody, Spinner, StatusBadge } from '@/components/ui';
import { Donut, LineChart, type DonutSegment } from '@/components/charts';
import type { DashboardStats, SubmissionStatus } from '@/types';
import {
  Inbox, AlertTriangle, Clock, CheckCircle, Send, RefreshCw,
  ArrowRight, ArrowUpRight, ArrowDownRight, Building2, Activity,
  TrendingUp, Shield, EyeOff, UserPlus, ShieldAlert,
} from 'lucide-react';
import Link from 'next/link';
import { format, formatDistanceToNow } from 'date-fns';
import { clsx } from 'clsx';

// ----------------------------------------------------------------
// Managerial Overview — top-level dashboard for super_admin /
// dept_head / management roles. Built as a one-glance view for
// university leadership: SLA compliance up top, six stat cards,
// status breakdown + volume trend, department performance,
// top form types, recent activity, and a "needs attention" panel
// surfacing escalated/overdue items with deep links.
// ----------------------------------------------------------------

type Period = 'week' | 'month' | 'semester';

const PERIOD_OPTS: { value: Period; label: string }[] = [
  { value: 'week',     label: '7 days'  },
  { value: 'month',    label: '30 days' },
  { value: 'semester', label: 'Semester' },
];

// Hex colors keyed by submission status — used for the donut segments
// and the status-breakdown legend. Mirrors STATUS_CONFIG visually but
// uses concrete hex values that SVG can render directly.
const STATUS_COLOR: Record<SubmissionStatus, string> = {
  draft:           '#9ca3af', // gray-400
  submitted:       '#f59e0b', // amber-500
  routed:          '#6366f1', // indigo-500
  in_review:       '#0ea5e9', // sky-500
  action_required: '#a855f7', // purple-500
  escalated:       '#eab308', // yellow-500
  approved:        '#3b82f6', // blue-500
  rejected:        '#ef4444', // red-500
  returned:        '#9ca3af', // gray-400
  completed:       '#22c55e', // green-500
  cancelled:       '#d1d5db', // gray-300
};

// Order matters for the legend — "active" statuses first, then resolved
const STATUS_ORDER: SubmissionStatus[] = [
  'submitted', 'in_review', 'action_required', 'escalated',
  'approved', 'completed', 'rejected', 'returned',
];

const STATUS_LABEL: Record<SubmissionStatus, string> = {
  draft:           'Draft',
  submitted:       'Pending',
  routed:          'Routed',
  in_review:       'In Review',
  action_required: 'Action Required',
  escalated:       'Escalated',
  approved:        'Approved',
  rejected:        'Rejected',
  returned:        'Returned',
  completed:       'Completed',
  cancelled:       'Cancelled',
};

// ----------------------------------------------------------------
// Page
// ----------------------------------------------------------------

export default function ManagerialOverviewPage() {
  const qc = useQueryClient();
  const [period, setPeriod] = useState<Period>('semester');

  const { data: stats, isLoading, dataUpdatedAt } = useQuery({
    queryKey: ['admin-dashboard', period],
    queryFn:  () => adminApi.dashboard({ period }),
    select:   (res) => res.data as DashboardStats,
    refetchInterval: 60_000,
  });

  if (isLoading || !stats) {
    return <div className="flex justify-center py-24"><Spinner /></div>;
  }

  return (
    <div className="p-6 space-y-6 max-w-[1600px]">
      <Header
        period={period}
        onPeriodChange={setPeriod}
        periodLabel={stats.period_label}
        slaCompliancePct={stats.sla_compliance_pct}
        onRefresh={() => qc.invalidateQueries({ queryKey: ['admin-dashboard'] })}
        lastUpdated={dataUpdatedAt}
        deptScoped={stats.departments === null}
      />

      <StatCards stats={stats} />

      <SecondaryKPIs stats={stats} />

      <div className="grid lg:grid-cols-3 gap-5">
        <StatusBreakdownCard stats={stats} />
        <div className="lg:col-span-2">
          <SubmissionVolumeCard stats={stats} />
        </div>
      </div>

      <div className="grid lg:grid-cols-5 gap-5">
        {stats.departments && (
          <div className="lg:col-span-3">
            <DepartmentPerformanceCard departments={stats.departments} />
          </div>
        )}
        <div className={stats.departments ? 'lg:col-span-2' : 'lg:col-span-5'}>
          <TopFormTypesCard items={stats.top_form_types} />
        </div>
      </div>

      <div className="grid lg:grid-cols-2 gap-5">
        <NeedsAttentionCard items={stats.needs_attention} />
        <RecentActivityCard activity={stats.recent_activity} />
      </div>
    </div>
  );
}

// ----------------------------------------------------------------
// Header — title, SLA pill, period selector, refresh, timestamp
// ----------------------------------------------------------------

function Header({
  period, onPeriodChange, periodLabel, slaCompliancePct, onRefresh, lastUpdated, deptScoped,
}: {
  period: Period;
  onPeriodChange: (p: Period) => void;
  periodLabel: string;
  slaCompliancePct: number | null;
  onRefresh: () => void;
  lastUpdated: number;
  deptScoped: boolean;
}) {
  const updatedTime = lastUpdated ? format(new Date(lastUpdated), 'HH:mm') : '—';

  // SLA compliance color — green ≥90, yellow ≥70, red below
  const slaColor =
    slaCompliancePct === null ? 'bg-gray-100 text-gray-500 border-gray-200'
    : slaCompliancePct >= 90  ? 'bg-green-50 text-green-700 border-green-200'
    : slaCompliancePct >= 70  ? 'bg-yellow-50 text-yellow-700 border-yellow-200'
    :                           'bg-red-50 text-red-700 border-red-200';

  return (
    <div className="flex items-start justify-between flex-wrap gap-4">
      <div>
        <h1 className="text-[22px] font-bold text-gray-900">Managerial Overview</h1>
        <p className="text-[13px] text-gray-500 mt-1">
          {deptScoped ? 'Your department' : 'All departments'} · {periodLabel}
        </p>
      </div>

      <div className="flex items-center gap-2 flex-wrap">
        {/* SLA Compliance — leadership KPI, prominent next to the period selector */}
        <div className={clsx(
          'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-[13px] font-medium',
          slaColor,
        )}>
          <Shield className="w-3.5 h-3.5" />
          <span className="text-[11px] uppercase tracking-wider opacity-75">SLA</span>
          <span className="font-bold tabular-nums">
            {slaCompliancePct !== null ? `${slaCompliancePct}%` : '—'}
          </span>
          <span className="text-[11px] opacity-75">on time</span>
        </div>

        <div className="inline-flex rounded-lg bg-gray-100 p-1">
          {PERIOD_OPTS.map((opt) => (
            <button
              key={opt.value}
              onClick={() => onPeriodChange(opt.value)}
              className={clsx(
                'px-3 py-1.5 text-[13px] rounded-md font-medium transition-colors',
                period === opt.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'
              )}
            >
              {opt.label}
            </button>
          ))}
        </div>
        <button
          onClick={onRefresh}
          className="inline-flex items-center gap-1.5 px-3 py-1.5 text-[13px] font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"
        >
          <RefreshCw className="w-3.5 h-3.5" />
          Refresh
        </button>
        <span className="text-[11px] text-gray-400">Updated {updatedTime}</span>
      </div>
    </div>
  );
}

// ----------------------------------------------------------------
// Six stat cards
// ----------------------------------------------------------------

type StatAccent = 'slate' | 'green' | 'orange' | 'red' | 'yellow' | 'blue';

const ACCENT_BORDER: Record<StatAccent, string> = {
  slate:  'border-l-slate-500',
  green:  'border-l-green-500',
  orange: 'border-l-orange-500',
  red:    'border-l-red-500',
  yellow: 'border-l-yellow-500',
  blue:   'border-l-blue-500',
};
const ACCENT_ICON: Record<StatAccent, string> = {
  slate:  'text-slate-500',
  green:  'text-green-500',
  orange: 'text-orange-500',
  red:    'text-red-500',
  yellow: 'text-yellow-500',
  blue:   'text-blue-500',
};
const ACCENT_VALUE: Record<StatAccent, string> = {
  slate:  'text-slate-800',
  green:  'text-green-700',
  orange: 'text-orange-600',
  red:    'text-red-600',
  yellow: 'text-yellow-600',
  blue:   'text-blue-700',
};

interface StatCardProps {
  label:       string;
  value:       string | number;
  Icon:        React.ElementType;
  accent:      StatAccent;
  subtitle?:   React.ReactNode;
  delta?:      number | null;
  deltaLabel?: string;
  href?:       string;
}

function StatCard({ label, value, Icon, accent, subtitle, delta, deltaLabel, href }: StatCardProps) {
  const inner = (
    <Card className={clsx(
      'border-l-4 h-full transition-shadow',
      href && 'hover:shadow-md cursor-pointer',
      ACCENT_BORDER[accent],
    )}>
      <CardBody className="space-y-2">
        <div className="flex items-center justify-between">
          <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">{label}</span>
          <Icon className={clsx('w-4 h-4', ACCENT_ICON[accent])} />
        </div>
        <div className={clsx('text-[28px] font-bold leading-tight', ACCENT_VALUE[accent])}>{value}</div>
        <div className="flex items-center gap-1 text-[11px] text-gray-500">
          {delta !== null && delta !== undefined && (
            <span className={clsx(
              'inline-flex items-center gap-0.5 font-semibold',
              delta > 0 ? 'text-green-600' : delta < 0 ? 'text-red-600' : 'text-gray-500',
            )}>
              {delta > 0 ? <ArrowUpRight className="w-3 h-3" /> : delta < 0 ? <ArrowDownRight className="w-3 h-3" /> : null}
              {delta > 0 ? '+' : ''}{delta}%
            </span>
          )}
          {subtitle ?? deltaLabel}
        </div>
      </CardBody>
    </Card>
  );
  return href ? <Link href={href}>{inner}</Link> : inner;
}

function StatCards({ stats }: { stats: DashboardStats }) {
  return (
    <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
      <StatCard
        label="Total Submitted"
        value={stats.total_submitted.toLocaleString()}
        Icon={Send}
        accent="slate"
        delta={stats.total_submitted_delta_pct}
        deltaLabel="vs. previous period"
      />
      <StatCard
        label="Completed"
        value={stats.completed.toLocaleString()}
        Icon={CheckCircle}
        accent="green"
        delta={stats.completed_delta_pct}
        deltaLabel="resolved in period"
      />
      <StatCard
        label="Pending Review"
        value={stats.pending_review.toLocaleString()}
        Icon={Clock}
        accent="orange"
        subtitle={
          <span className="inline-flex items-center gap-0.5">
            current queue <ArrowRight className="w-3 h-3" />
          </span>
        }
        href="/admin/submissions?status=submitted"
      />
      <StatCard
        label="Overdue"
        value={stats.overdue.toLocaleString()}
        Icon={AlertTriangle}
        accent="red"
        subtitle="SLA breached"
        href="/admin/submissions?sla_breached=1"
      />
      <StatCard
        label="Escalated"
        value={stats.escalated.toLocaleString()}
        Icon={Inbox}
        accent="yellow"
        subtitle="needs attention"
        href="/admin/submissions?status=escalated"
      />
      <StatCard
        label="Avg Resolution"
        value={stats.avg_resolution_days !== null ? `${stats.avg_resolution_days}d` : '—'}
        Icon={Clock}
        accent="blue"
        subtitle={`avg · ${stats.period_label.toLowerCase()}`}
      />
    </div>
  );
}

// ----------------------------------------------------------------
// Secondary KPIs — anonymous + new students + volume peak
// Smaller, contextual signals leadership cares about
// ----------------------------------------------------------------

function SecondaryKPIs({ stats }: { stats: DashboardStats }) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      <SecondaryKPI
        Icon={EyeOff}
        label="Anonymous Submissions"
        value={stats.anonymous_in_period.toLocaleString()}
        helper={stats.anonymous_in_period > 0 ? 'Sensitive — review for sentiment' : 'No anonymous submissions in period'}
        tone={stats.anonymous_in_period > 0 ? 'attention' : 'neutral'}
      />
      <SecondaryKPI
        Icon={UserPlus}
        label="New Students"
        value={stats.new_students_in_period.toLocaleString()}
        helper="Registered in this period"
        tone="neutral"
      />
      <SecondaryKPI
        Icon={TrendingUp}
        label="Volume Peak"
        value={stats.volume_peak.toLocaleString()}
        helper={stats.period === 'semester' ? 'Submissions in busiest month' : 'Submissions in busiest day'}
        tone="neutral"
      />
    </div>
  );
}

function SecondaryKPI({ Icon, label, value, helper, tone }: {
  Icon: React.ElementType;
  label: string;
  value: string;
  helper: string;
  tone: 'neutral' | 'attention';
}) {
  return (
    <Card>
      <CardBody className="flex items-center gap-3">
        <div className={clsx(
          'w-9 h-9 rounded-lg flex items-center justify-center shrink-0',
          tone === 'attention' ? 'bg-purple-50 text-purple-600' : 'bg-gray-50 text-gray-500',
        )}>
          <Icon className="w-4 h-4" />
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex items-baseline gap-2">
            <span className="text-lg font-bold text-gray-900 tabular-nums">{value}</span>
            <span className="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">{label}</span>
          </div>
          <p className="text-[11px] text-gray-400 mt-0.5">{helper}</p>
        </div>
      </CardBody>
    </Card>
  );
}

// ----------------------------------------------------------------
// Status Breakdown — donut stacked over legend
// ----------------------------------------------------------------

function StatusBreakdownCard({ stats }: { stats: DashboardStats }) {
  const segments: DonutSegment[] = useMemo(() =>
    STATUS_ORDER
      .filter(s => (stats.status_counts[s] ?? 0) > 0)
      .map(s => ({
        label: STATUS_LABEL[s],
        value: stats.status_counts[s] ?? 0,
        color: STATUS_COLOR[s],
      })),
  [stats.status_counts]);

  return (
    <Card>
      <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
          <h2 className="text-[13px] font-semibold text-gray-900">Status Breakdown</h2>
          <p className="text-[11px] text-gray-400 mt-0.5">Current snapshot — all submissions</p>
        </div>
        <span className="text-[11px] font-medium text-gray-600 bg-gray-100 px-2.5 py-1 rounded-full">
          {stats.total_snapshot.toLocaleString()} total
        </span>
      </div>
      <CardBody>
        <div className="flex flex-col items-center gap-5">
          <Donut
            segments={segments}
            centerValue={stats.total_active}
            centerLabel="ACTIVE"
            size={200}
            thickness={24}
          />
          <ul className="w-full space-y-1.5">
            {STATUS_ORDER.map((s) => {
              const count = stats.status_counts[s] ?? 0;
              const pct   = stats.total_snapshot > 0
                ? Math.round((count / stats.total_snapshot) * 100)
                : 0;
              return (
                <li key={s} className="flex items-center gap-2 text-[13px]">
                  <span
                    className="w-2.5 h-2.5 rounded-full shrink-0"
                    style={{ background: STATUS_COLOR[s] }}
                  />
                  <span className="flex-1 text-gray-700">{STATUS_LABEL[s]}</span>
                  <span className="font-semibold text-gray-900 tabular-nums w-12 text-right">{count}</span>
                  <span className="text-[11px] text-gray-400 tabular-nums w-10 text-right">{pct}%</span>
                </li>
              );
            })}
          </ul>
        </div>
      </CardBody>
    </Card>
  );
}

// ----------------------------------------------------------------
// Submission Volume — line chart
// ----------------------------------------------------------------

function SubmissionVolumeCard({ stats }: { stats: DashboardStats }) {
  const granularity = stats.period === 'semester' ? 'Monthly' : 'Daily';
  return (
    <Card>
      <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
          <h2 className="text-[13px] font-semibold text-gray-900">Submission Volume</h2>
          <p className="text-[11px] text-gray-400 mt-0.5">
            {granularity} submissions · {stats.period_label}
          </p>
        </div>
        {stats.volume_peak > 0 && (
          <span className="text-[11px] font-medium text-gray-600 bg-gray-100 px-2.5 py-1 rounded-full">
            Peak: {stats.volume_peak}
          </span>
        )}
      </div>
      <CardBody>
        <div className="h-[280px]">
          <LineChart data={stats.submission_volume} height={280} color="#1e3a8a" />
        </div>
      </CardBody>
    </Card>
  );
}

// ----------------------------------------------------------------
// Department Performance — table with SLA compliance bar
// ----------------------------------------------------------------

function DepartmentPerformanceCard({ departments }: { departments: NonNullable<DashboardStats['departments']> }) {
  return (
    <Card>
      <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
        <Building2 className="w-4 h-4 text-gray-400" />
        <h2 className="text-[13px] font-semibold text-gray-900">Department Performance</h2>
        <span className="text-[11px] text-gray-400 ml-auto">live · current period</span>
      </div>
      {departments.length === 0 ? (
        <CardBody>
          <p className="text-sm text-gray-400 text-center py-6">No active departments.</p>
        </CardBody>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-[13px]">
            <thead>
              <tr className="border-b border-gray-100 text-[11px] uppercase tracking-wider text-gray-400">
                <th className="px-6 py-2 text-left font-semibold">Department</th>
                <th className="px-3 py-2 text-right font-semibold">Submitted</th>
                <th className="px-3 py-2 text-right font-semibold">Open</th>
                <th className="px-3 py-2 text-right font-semibold">Breached</th>
                <th className="px-6 py-2 font-semibold min-w-[160px]">SLA Health</th>
              </tr>
            </thead>
            <tbody>
              {departments.map((d) => {
                const totalForPct = d.period_total > 0 ? d.period_total : 1;
                const compliance  = Math.max(0, Math.round(((totalForPct - d.breached_count) / totalForPct) * 100));
                const barColor    = compliance >= 90 ? 'bg-green-500' : compliance >= 70 ? 'bg-yellow-500' : 'bg-red-500';
                return (
                  <tr key={d.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                    <td className="px-6 py-3 font-medium text-gray-900">{d.name}</td>
                    <td className="px-3 py-3 text-right text-gray-700 tabular-nums">{d.period_total}</td>
                    <td className="px-3 py-3 text-right text-gray-700 tabular-nums">{d.open_count}</td>
                    <td className="px-3 py-3 text-right tabular-nums">
                      {d.breached_count > 0
                        ? <span className="text-red-600 font-semibold">{d.breached_count}</span>
                        : <span className="text-gray-400">0</span>}
                    </td>
                    <td className="px-6 py-3">
                      <div className="flex items-center gap-3">
                        <div className="flex-1 bg-gray-100 rounded-full h-1.5">
                          <div className={clsx('h-1.5 rounded-full', barColor)} style={{ width: `${compliance}%` }} />
                        </div>
                        <span className="text-[11px] font-medium text-gray-600 tabular-nums w-10 text-right">{compliance}%</span>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}

// ----------------------------------------------------------------
// Top Form Types — what students are asking for most
// ----------------------------------------------------------------

function TopFormTypesCard({ items }: { items: { name: string; count: number }[] }) {
  const max = items.length > 0 ? Math.max(...items.map(i => i.count), 1) : 1;
  return (
    <Card>
      <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
        <TrendingUp className="w-4 h-4 text-gray-400" />
        <h2 className="text-[13px] font-semibold text-gray-900">Top Request Types</h2>
        <span className="text-[11px] text-gray-400 ml-auto">last 30 days</span>
      </div>
      {items.length === 0 ? (
        <CardBody>
          <p className="text-sm text-gray-400 text-center py-6">No requests yet.</p>
        </CardBody>
      ) : (
        <CardBody>
          <ul className="space-y-3">
            {items.map((ft, i) => (
              <li key={ft.name} className="flex items-center gap-3 text-[13px]">
                <span className="w-5 h-5 rounded-full bg-brand-50 text-brand-500 text-[11px] font-bold flex items-center justify-center shrink-0">
                  {i + 1}
                </span>
                <span className="flex-1 text-gray-700 truncate" title={ft.name}>{ft.name}</span>
                <div className="w-20 bg-gray-100 rounded-full h-1.5 shrink-0">
                  <div className="bg-brand-500 h-1.5 rounded-full" style={{ width: `${(ft.count / max) * 100}%` }} />
                </div>
                <span className="font-semibold text-gray-900 tabular-nums w-8 text-right">{ft.count}</span>
              </li>
            ))}
          </ul>
        </CardBody>
      )}
    </Card>
  );
}

// ----------------------------------------------------------------
// Needs Attention — escalated + most overdue, deep-link list
// ----------------------------------------------------------------

function NeedsAttentionCard({ items }: { items: DashboardStats['needs_attention'] }) {
  return (
    <Card>
      <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
        <ShieldAlert className="w-4 h-4 text-red-500" />
        <h2 className="text-[13px] font-semibold text-gray-900">Needs Attention</h2>
        <span className="text-[11px] text-gray-400 ml-auto">escalated + most overdue</span>
      </div>
      {items.length === 0 ? (
        <CardBody>
          <p className="text-sm text-gray-400 text-center py-6">
            <CheckCircle className="w-5 h-5 inline-block text-green-500 mr-1.5 -mt-0.5" />
            All caught up — nothing escalated or overdue.
          </p>
        </CardBody>
      ) : (
        <ul className="divide-y divide-gray-50 max-h-[420px] overflow-y-auto">
          {items.map((s) => (
            <li key={s.reference_no}>
              <Link
                href={`/admin/submissions/${s.reference_no}`}
                className="flex items-start gap-3 px-6 py-3 hover:bg-gray-50 transition-colors"
              >
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="text-[11px] font-mono font-semibold text-brand-600">{s.reference_no}</span>
                    <StatusBadge status={s.status} />
                  </div>
                  <div className="text-[12px] text-gray-500 mt-1 truncate">
                    {s.form_type ?? 'Unknown'}{s.department && <> · {s.department}</>}
                  </div>
                  {s.assigned_to && (
                    <div className="text-[11px] text-gray-400 mt-0.5">Assigned: {s.assigned_to}</div>
                  )}
                </div>
                <div className="text-right shrink-0">
                  {s.hours_overdue !== null
                    ? <span className="text-[12px] font-bold text-red-600 tabular-nums">+{s.hours_overdue}h</span>
                    : <span className="text-[11px] text-yellow-600 font-medium">Escalated</span>}
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

// ----------------------------------------------------------------
// Recent Activity — last 10 status changes
// ----------------------------------------------------------------

function RecentActivityCard({ activity }: { activity: DashboardStats['recent_activity'] }) {
  return (
    <Card>
      <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
        <Activity className="w-4 h-4 text-gray-400" />
        <h2 className="text-[13px] font-semibold text-gray-900">Recent Activity</h2>
        <span className="text-[11px] text-gray-400 ml-auto">last 10 status changes</span>
      </div>
      {activity.length === 0 ? (
        <CardBody>
          <p className="text-sm text-gray-400 text-center py-6">No activity yet.</p>
        </CardBody>
      ) : (
        <ul className="divide-y divide-gray-50 max-h-[420px] overflow-y-auto">
          {activity.map((a) => (
            <li key={a.id} className="px-6 py-3">
              <div className="flex items-start gap-3">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    {a.reference_no && (
                      <Link
                        href={`/admin/submissions/${a.reference_no}`}
                        className="text-[11px] font-mono font-semibold text-brand-600 hover:underline"
                      >
                        {a.reference_no}
                      </Link>
                    )}
                    <StatusBadge status={a.to_status} />
                  </div>
                  <div className="text-[12px] text-gray-500 mt-1">
                    {a.form_type ?? 'Unknown form'}
                    {a.department && <> · {a.department}</>}
                  </div>
                  {a.changed_by && (
                    <div className="text-[11px] text-gray-400 mt-0.5">by {a.changed_by}</div>
                  )}
                </div>
                <div className="text-[11px] text-gray-400 shrink-0 text-right">
                  {formatDistanceToNow(new Date(a.changed_at), { addSuffix: true })}
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}
