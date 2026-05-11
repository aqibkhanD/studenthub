'use client';
import { useQuery } from '@tanstack/react-query';
import { studentApi } from '@/lib/api';
import { useAuthStore } from '@/store/authStore';
import { Card, CardBody, StatusBadge, Spinner, EmptyState } from '@/components/ui';
import { ClipboardList, Clock, CheckCircle, AlertCircle } from 'lucide-react';
import Link from 'next/link';
import { formatDistanceToNow } from 'date-fns';
import type { Submission, PaginatedResponse } from '@/types';

export default function StudentDashboard() {
  const user = useAuthStore((s) => s.user);

  const { data, isLoading } = useQuery({
    queryKey: ['student-submissions', 'recent'],
    queryFn:  () => studentApi.submissions({ per_page: 5 }),
    select:   (res) => res.data as PaginatedResponse<Submission>,
  });

  const submissions = data?.data ?? [];
  const total       = data?.total ?? 0;
  const pending     = submissions.filter(s => ['submitted','routed','in_review','action_required','escalated'].includes(s.status)).length;
  const approved    = submissions.filter(s => s.status === 'approved' || s.status === 'completed').length;
  const breached    = submissions.filter(s => s.sla_breached).length;

  const stats = [
    { label: 'Total Requests',   value: total,    Icon: ClipboardList, color: 'text-blue-600',   bg: 'bg-blue-50' },
    { label: 'Pending',          value: pending,  Icon: Clock,         color: 'text-yellow-600', bg: 'bg-yellow-50' },
    { label: 'Approved',         value: approved, Icon: CheckCircle,   color: 'text-green-600',  bg: 'bg-green-50' },
    { label: 'Overdue',          value: breached, Icon: AlertCircle,   color: 'text-red-600',    bg: 'bg-red-50' },
  ];

  return (
    <div className="p-6 max-w-4xl mx-auto space-y-6">
      {/* Welcome */}
      <div>
        <h1 className="text-xl font-bold text-gray-900">Welcome back, {user?.name?.split(' ')[0]}</h1>
        <p className="text-sm text-gray-500 mt-0.5">
          {user?.program && `${user.program} · `}
          {user?.student_id}
        </p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        {stats.map(({ label, value, Icon, color, bg }) => (
          <Card key={label}>
            <CardBody className="flex items-center gap-3 py-4">
              <div className={`w-10 h-10 rounded-xl ${bg} flex items-center justify-center shrink-0`}>
                <Icon className={`w-5 h-5 ${color}`} />
              </div>
              <div>
                <div className="text-xl font-bold text-gray-900">{value}</div>
                <div className="text-xs text-gray-500">{label}</div>
              </div>
            </CardBody>
          </Card>
        ))}
      </div>

      {/* Quick actions */}
      <Card>
        <CardBody>
          <h2 className="text-sm font-semibold text-gray-700 mb-3">Quick Actions</h2>
          <div className="flex flex-wrap gap-2">
            <Link href="/forms" className="px-4 py-2 bg-brand-500 text-white text-sm rounded-lg font-medium hover:bg-brand-600 transition-colors">
              Submit a Request
            </Link>
            <Link href="/submissions" className="px-4 py-2 border border-gray-300 text-gray-700 text-sm rounded-lg font-medium hover:bg-gray-50 transition-colors">
              View All Submissions
            </Link>
          </div>
        </CardBody>
      </Card>

      {/* Recent submissions */}
      <Card>
        <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 className="text-sm font-semibold text-gray-900">Recent Submissions</h2>
          <Link href="/submissions" className="text-xs text-brand-500 hover:underline">View all</Link>
        </div>
        {isLoading ? (
          <div className="flex justify-center py-12"><Spinner /></div>
        ) : submissions.length === 0 ? (
          <EmptyState title="No submissions yet" description="Submit your first request to get started." icon={ClipboardList} />
        ) : (
          <ul className="divide-y divide-gray-50">
            {submissions.map((s) => (
              <li key={s.id}>
                <Link href={`/submissions/${s.reference_no}`} className="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors">
                  <div>
                    <div className="text-sm font-medium text-gray-900">{s.form_type?.name}</div>
                    <div className="text-xs text-gray-400 mt-0.5">
                      {s.reference_no} · {s.submitted_at ? formatDistanceToNow(new Date(s.submitted_at), { addSuffix: true }) : 'Draft'}
                    </div>
                  </div>
                  <StatusBadge status={s.status} />
                </Link>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}
