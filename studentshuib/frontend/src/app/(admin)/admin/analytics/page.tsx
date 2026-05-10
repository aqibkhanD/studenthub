'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { superApi, adminApi } from '@/lib/api';
import { Card, CardBody, Spinner } from '@/components/ui';
import {
  Users, FileText, CheckCircle, AlertTriangle, Clock, TrendingUp, BarChart2, Building2, Download,
} from 'lucide-react';

// ---- Types ----------------------------------------------------------------

interface Overview {
  period_days: number;
  total_students: number;
  total_submissions: number;
  submissions_in_period: number;
  resolved_in_period: number;
  avg_resolution_hours: number;
  sla_compliance_pct: number;
}

interface SlaReport {
  breached_by_department: Array<{ department: string; breached_count: number }>;
  overdue_submissions: Array<{
    reference_no: string;
    form_type: string | null;
    student: string | null;
    department: string | null;
    assigned_to: string | null;
    status: string;
    sla_deadline: string | null;
    hours_overdue: number;
  }>;
}

interface DeptReport {
  period_days: number;
  departments: Array<{
    id: number;
    name: string;
    total_submissions: number;
    open_submissions: number;
    sla_breached: number;
  }>;
}

// ---- Helpers ---------------------------------------------------------------

function StatCard({
  label, value, sub, Icon, color, bg,
}: {
  label: string; value: string | number; sub?: string;
  Icon: React.ElementType; color: string; bg: string;
}) {
  return (
    <Card>
      <CardBody className="flex items-center gap-4">
        <div className={`w-11 h-11 rounded-xl ${bg} flex items-center justify-center shrink-0`}>
          <Icon className={`w-5 h-5 ${color}`} />
        </div>
        <div className="min-w-0">
          <div className="text-2xl font-bold text-gray-900 truncate">{value}</div>
          <div className="text-xs text-gray-500">{label}</div>
          {sub && <div className="text-xs text-gray-400 mt-0.5">{sub}</div>}
        </div>
      </CardBody>
    </Card>
  );
}

function SlaBar({ pct }: { pct: number }) {
  const colour = pct >= 90 ? 'bg-green-500' : pct >= 70 ? 'bg-yellow-500' : 'bg-red-500';
  return (
    <div className="flex items-center gap-3">
      <div className="flex-1 bg-gray-100 rounded-full h-2">
        <div className={`${colour} h-2 rounded-full transition-all`} style={{ width: `${pct}%` }} />
      </div>
      <span className="text-sm font-semibold text-gray-700 w-12 text-right">{pct}%</span>
    </div>
  );
}

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, string> = {
    submitted:       'bg-blue-50 text-blue-700',
    routed:          'bg-indigo-50 text-indigo-700',
    in_review:       'bg-yellow-50 text-yellow-700',
    action_required: 'bg-orange-50 text-orange-700',
    escalated:       'bg-red-50 text-red-700',
    approved:        'bg-green-50 text-green-700',
    rejected:        'bg-gray-100 text-gray-600',
    returned:        'bg-purple-50 text-purple-700',
  };
  const cls = map[status] ?? 'bg-gray-100 text-gray-600';
  return (
    <span className={`inline-block px-2 py-0.5 rounded text-xs font-medium ${cls}`}>
      {status.replace('_', ' ')}
    </span>
  );
}

// ---- Page -----------------------------------------------------------------

const PERIOD_OPTIONS = [
  { label: '7 days',  value: 7 },
  { label: '30 days', value: 30 },
  { label: '90 days', value: 90 },
];

export default function AnalyticsPage() {
  const [period, setPeriod]       = useState(30);
  const [exporting, setExporting] = useState(false);

  const handleExportPdf = async () => {
    setExporting(true);
    try {
      const res  = await adminApi.exportPdf({ days: period });
      const url  = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
      const link = document.createElement('a');
      link.href  = url;
      link.setAttribute('download', `analytics_report_${new Date().toISOString().slice(0, 10)}.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch {
      // silent — backend may need dompdf installed; see SETUP.md
    } finally {
      setExporting(false);
    }
  };

  const { data: overview, isLoading: ovLoading } = useQuery({
    queryKey: ['analytics-overview', period],
    queryFn:  () => superApi.analytics(period),
    select:   (r) => r.data as Overview,
  });

  const { data: sla, isLoading: slaLoading } = useQuery({
    queryKey: ['analytics-sla'],
    queryFn:  () => superApi.slaReport(),
    select:   (r) => r.data as SlaReport,
  });

  const { data: deptRpt, isLoading: deptLoading } = useQuery({
    queryKey: ['analytics-departments', period],
    queryFn:  () => superApi.deptReport(period),
    select:   (r) => r.data as DeptReport,
  });

  const isLoading = ovLoading || slaLoading || deptLoading;

  return (
    <div className="p-6 space-y-8">

      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-xl font-bold text-gray-900">Analytics</h1>
        <div className="flex items-center gap-2">
          {PERIOD_OPTIONS.map((opt) => (
            <button
              key={opt.value}
              onClick={() => setPeriod(opt.value)}
              className={`px-3 py-1.5 text-sm rounded-lg font-medium transition-colors ${
                period === opt.value
                  ? 'bg-brand-500 text-white'
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              }`}
            >
              {opt.label}
            </button>
          ))}
          <button
            onClick={handleExportPdf}
            disabled={exporting || isLoading}
            className="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors"
          >
            {exporting ? <Spinner className="w-4 h-4" /> : <Download className="w-4 h-4" />}
            Export PDF
          </button>
        </div>
      </div>

      {isLoading && (
        <div className="flex justify-center py-20">
          <Spinner />
        </div>
      )}

      {!isLoading && (
        <>
          {/* ---- Overview stat cards ---- */}
          <section className="space-y-3">
            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide">Overview</h2>
            <div className="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
              <StatCard
                label="Total Students"
                value={overview?.total_students?.toLocaleString() ?? '—'}
                Icon={Users}
                color="text-brand-600"
                bg="bg-brand-50"
              />
              <StatCard
                label="Total Submissions"
                value={overview?.total_submissions?.toLocaleString() ?? '—'}
                Icon={FileText}
                color="text-blue-600"
                bg="bg-blue-50"
              />
              <StatCard
                label={`Submitted (${period}d)`}
                value={overview?.submissions_in_period?.toLocaleString() ?? '—'}
                Icon={TrendingUp}
                color="text-indigo-600"
                bg="bg-indigo-50"
              />
              <StatCard
                label={`Resolved (${period}d)`}
                value={overview?.resolved_in_period?.toLocaleString() ?? '—'}
                Icon={CheckCircle}
                color="text-green-600"
                bg="bg-green-50"
              />
              <StatCard
                label="Avg Resolution"
                value={`${overview?.avg_resolution_hours ?? '—'}h`}
                Icon={Clock}
                color="text-orange-600"
                bg="bg-orange-50"
              />
              <StatCard
                label="SLA Compliance"
                value={`${overview?.sla_compliance_pct ?? '—'}%`}
                Icon={AlertTriangle}
                color={
                  (overview?.sla_compliance_pct ?? 100) >= 90
                    ? 'text-green-600'
                    : (overview?.sla_compliance_pct ?? 100) >= 70
                    ? 'text-yellow-600'
                    : 'text-red-600'
                }
                bg={
                  (overview?.sla_compliance_pct ?? 100) >= 90
                    ? 'bg-green-50'
                    : (overview?.sla_compliance_pct ?? 100) >= 70
                    ? 'bg-yellow-50'
                    : 'bg-red-50'
                }
              />
            </div>
          </section>

          {/* ---- Department breakdown ---- */}
          <section className="space-y-3">
            <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide flex items-center gap-2">
              <Building2 className="w-4 h-4" />
              Department Performance ({period} days)
            </h2>
            <Card>
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-gray-100 text-left">
                      <th className="px-4 py-3 font-semibold text-gray-600">Department</th>
                      <th className="px-4 py-3 font-semibold text-gray-600 text-right">Submitted</th>
                      <th className="px-4 py-3 font-semibold text-gray-600 text-right">Open</th>
                      <th className="px-4 py-3 font-semibold text-gray-600 text-right">SLA Breached</th>
                      <th className="px-4 py-3 font-semibold text-gray-600 min-w-[180px]">SLA Health</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(deptRpt?.departments ?? []).length === 0 ? (
                      <tr>
                        <td colSpan={5} className="px-4 py-8 text-center text-gray-400">No data available.</td>
                      </tr>
                    ) : (
                      (deptRpt?.departments ?? []).map((dept) => {
                        const breachPct = dept.total_submissions > 0
                          ? Math.round((dept.sla_breached / dept.total_submissions) * 100)
                          : 0;
                        const compliancePct = 100 - breachPct;
                        return (
                          <tr key={dept.id} className="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                            <td className="px-4 py-3 font-medium text-gray-900">{dept.name}</td>
                            <td className="px-4 py-3 text-right text-gray-700">{dept.total_submissions}</td>
                            <td className="px-4 py-3 text-right text-gray-700">{dept.open_submissions}</td>
                            <td className="px-4 py-3 text-right">
                              {dept.sla_breached > 0 ? (
                                <span className="text-red-600 font-semibold">{dept.sla_breached}</span>
                              ) : (
                                <span className="text-green-600">0</span>
                              )}
                            </td>
                            <td className="px-4 py-3">
                              <SlaBar pct={compliancePct} />
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </Card>
          </section>

          {/* ---- SLA breaches by dept + overdue list ---- */}
          <div className="grid lg:grid-cols-2 gap-6">

            {/* Breaches by department bar chart (CSS only) */}
            <section className="space-y-3">
              <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide flex items-center gap-2">
                <BarChart2 className="w-4 h-4" />
                Current SLA Breaches by Department
              </h2>
              <Card>
                <CardBody>
                  {(sla?.breached_by_department ?? []).length === 0 ? (
                    <p className="text-sm text-gray-400 py-4 text-center">No active SLA breaches.</p>
                  ) : (
                    <div className="space-y-3">
                      {sla!.breached_by_department.map((row) => {
                        const maxCount = Math.max(...sla!.breached_by_department.map((r) => r.breached_count), 1);
                        return (
                          <div key={row.department} className="flex items-center gap-3">
                            <span className="text-xs text-gray-500 w-32 shrink-0 truncate" title={row.department}>
                              {row.department}
                            </span>
                            <div className="flex-1 bg-red-50 rounded-full h-2">
                              <div
                                className="bg-red-500 h-2 rounded-full"
                                style={{ width: `${(row.breached_count / maxCount) * 100}%` }}
                              />
                            </div>
                            <span className="text-xs font-bold text-red-600 w-6 text-right">
                              {row.breached_count}
                            </span>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </CardBody>
              </Card>
            </section>

            {/* Overdue submissions list */}
            <section className="space-y-3">
              <h2 className="text-sm font-semibold text-gray-500 uppercase tracking-wide">
                Most Overdue Submissions
              </h2>
              <Card>
                <div className="divide-y divide-gray-50 max-h-72 overflow-y-auto">
                  {(sla?.overdue_submissions ?? []).length === 0 ? (
                    <p className="text-sm text-gray-400 p-4 text-center">No overdue submissions.</p>
                  ) : (
                    (sla?.overdue_submissions ?? []).slice(0, 10).map((s) => (
                      <div key={s.reference_no} className="px-4 py-3 flex items-start gap-3">
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center gap-2 flex-wrap">
                            <span className="text-xs font-mono font-semibold text-brand-600">{s.reference_no}</span>
                            <StatusBadge status={s.status} />
                          </div>
                          <div className="text-xs text-gray-500 mt-0.5 truncate">
                            {s.form_type ?? 'Unknown type'} · {s.department ?? '—'}
                          </div>
                          {s.assigned_to && (
                            <div className="text-xs text-gray-400">Assigned: {s.assigned_to}</div>
                          )}
                        </div>
                        <div className="text-xs font-bold text-red-600 shrink-0 text-right">
                          +{s.hours_overdue}h
                        </div>
                      </div>
                    ))
                  )}
                </div>
                {(sla?.overdue_submissions?.length ?? 0) > 10 && (
                  <div className="px-4 py-2 border-t border-gray-100 text-xs text-gray-400">
                    Showing 10 of {sla!.overdue_submissions.length} overdue submissions.
                  </div>
                )}
              </Card>
            </section>
          </div>
        </>
      )}
    </div>
  );
}
