'use client';
import { useQuery } from '@tanstack/react-query';
import { adminApi } from '@/lib/api';
import { Card, CardBody, Spinner } from '@/components/ui';
import type { DashboardStats } from '@/types';
import { Inbox, AlertTriangle, Clock, CheckCircle, TrendingUp } from 'lucide-react';
import Link from 'next/link';

export default function AdminDashboard() {
  const { data: stats, isLoading } = useQuery({
    queryKey: ['admin-dashboard'],
    queryFn:  () => adminApi.dashboard(),
    select:   (res) => res.data as DashboardStats,
    refetchInterval: 60_000, // refresh every minute
  });

  if (isLoading) return <div className="flex justify-center py-24"><Spinner /></div>;

  const cards = [
    { label: 'Open',         value: stats?.total_open ?? 0,      Icon: Inbox,         color: 'text-blue-600',   bg: 'bg-blue-50' },
    { label: 'SLA Breached', value: stats?.sla_breached ?? 0,    Icon: AlertTriangle, color: 'text-red-600',    bg: 'bg-red-50' },
    { label: 'Unassigned',   value: stats?.unassigned ?? 0,      Icon: Clock,         color: 'text-orange-600', bg: 'bg-orange-50' },
    { label: 'In Review',    value: stats?.status_counts?.in_review ?? 0, Icon: CheckCircle, color: 'text-yellow-600', bg: 'bg-yellow-50' },
  ];

  return (
    <div className="p-6 space-y-6">
      <h1 className="text-xl font-bold text-gray-900">Dashboard</h1>

      {/* Stat cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {cards.map(({ label, value, Icon, color, bg }) => (
          <Card key={label}>
            <CardBody className="flex items-center gap-3">
              <div className={`w-10 h-10 rounded-xl ${bg} flex items-center justify-center shrink-0`}>
                <Icon className={`w-5 h-5 ${color}`} />
              </div>
              <div>
                <div className="text-2xl font-bold text-gray-900">{value}</div>
                <div className="text-xs text-gray-500">{label}</div>
              </div>
            </CardBody>
          </Card>
        ))}
      </div>

      <div className="grid lg:grid-cols-2 gap-6">
        {/* Recent activity */}
        <Card>
          <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
            <TrendingUp className="w-4 h-4 text-gray-400" />
            <h2 className="text-sm font-semibold text-gray-900">Submissions (last 7 days)</h2>
          </div>
          <CardBody>
            {stats?.recent_by_day && stats.recent_by_day.length > 0 ? (
              <div className="space-y-2">
                {stats.recent_by_day.map((d) => (
                  <div key={d.date} className="flex items-center gap-3">
                    <span className="text-xs text-gray-400 w-24 shrink-0">{new Date(d.date).toLocaleDateString('en-BD', { weekday: 'short', month: 'short', day: 'numeric' })}</span>
                    <div className="flex-1 bg-gray-100 rounded-full h-2">
                      <div className="bg-brand-500 h-2 rounded-full" style={{ width: `${Math.min(100, (d.count / Math.max(...stats.recent_by_day.map(x => x.count), 1)) * 100)}%` }} />
                    </div>
                    <span className="text-xs font-medium text-gray-700 w-6 text-right">{d.count}</span>
                  </div>
                ))}
              </div>
            ) : <p className="text-sm text-gray-400">No submissions in the last 7 days.</p>}
          </CardBody>
        </Card>

        {/* Top form types */}
        <Card>
          <div className="px-6 py-4 border-b border-gray-100">
            <h2 className="text-sm font-semibold text-gray-900">Top Request Types (30 days)</h2>
          </div>
          <CardBody>
            {stats?.top_form_types && stats.top_form_types.length > 0 ? (
              <ul className="space-y-3">
                {stats.top_form_types.map((ft, i) => (
                  <li key={ft.name} className="flex items-center gap-3 text-sm">
                    <span className="w-5 h-5 rounded-full bg-brand-50 text-brand-500 text-xs font-bold flex items-center justify-center shrink-0">{i + 1}</span>
                    <span className="flex-1 text-gray-700">{ft.name}</span>
                    <span className="font-semibold text-gray-900">{ft.count}</span>
                  </li>
                ))}
              </ul>
            ) : <p className="text-sm text-gray-400">No data yet.</p>}
          </CardBody>
        </Card>
      </div>

      {/* Quick actions */}
      <Card>
        <CardBody>
          <div className="flex flex-wrap gap-3">
            <Link href="/admin/submissions?status=submitted" className="px-4 py-2 bg-brand-500 text-white text-sm rounded-lg font-medium hover:bg-brand-600">
              Review Pending
            </Link>
            {(stats?.sla_breached ?? 0) > 0 && (
              <Link href="/admin/submissions?sla_breached=1" className="px-4 py-2 bg-red-600 text-white text-sm rounded-lg font-medium hover:bg-red-700">
                {stats!.sla_breached} Overdue — Review Now
              </Link>
            )}
          </div>
        </CardBody>
      </Card>
    </div>
  );
}
