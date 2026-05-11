'use client';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { studentApi } from '@/lib/api';
import { Card, StatusBadge, Spinner, EmptyState, Select } from '@/components/ui';
import type { PaginatedResponse, Submission, SubmissionStatus } from '@/types';
import Link from 'next/link';
import { formatDistanceToNow } from 'date-fns';
import { ClipboardList, AlertCircle } from 'lucide-react';

const STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'submitted',       label: 'Submitted' },
  { value: 'in_review',       label: 'In Review' },
  { value: 'action_required', label: 'Action Required' },
  { value: 'approved',        label: 'Approved' },
  { value: 'rejected',        label: 'Rejected' },
  { value: 'returned',        label: 'Returned' },
  { value: 'completed',       label: 'Completed' },
];

export default function MySubmissionsPage() {
  const [status, setStatus] = useState('');
  const [page,   setPage]   = useState(1);

  const { data, isLoading } = useQuery({
    queryKey: ['student-submissions', status, page],
    queryFn:  () => studentApi.submissions({ status: status || undefined, page, per_page: 15 }),
    select:   (res) => res.data as PaginatedResponse<Submission>,
  });

  const submissions = data?.data ?? [];

  return (
    <div className="p-6 max-w-4xl mx-auto space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">My Submissions</h1>
        <Link href="/forms" className="px-4 py-2 bg-brand-500 text-white text-sm rounded-lg font-medium hover:bg-brand-600">
          New Request
        </Link>
      </div>

      {/* Filter */}
      <div className="w-48">
        <Select
          options={STATUS_OPTIONS}
          value={status}
          onChange={e => { setStatus(e.target.value); setPage(1); }}
        />
      </div>

      <Card>
        {isLoading ? (
          <div className="flex justify-center py-16"><Spinner /></div>
        ) : submissions.length === 0 ? (
          <EmptyState title="No submissions found" description="Submit a request to see it here." icon={ClipboardList} />
        ) : (
          <>
            <ul className="divide-y divide-gray-50">
              {submissions.map((s) => (
                <li key={s.id}>
                  <Link href={`/submissions/${s.reference_no}`} className="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 transition-colors">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <span className="text-sm font-medium text-gray-900">{s.form_type?.name}</span>
                        {s.sla_breached && <AlertCircle className="w-3.5 h-3.5 text-red-500 shrink-0" />}
                      </div>
                      <div className="text-xs text-gray-400 mt-0.5">
                        {s.reference_no} · {s.department?.name}
                        {s.submitted_at && ` · ${formatDistanceToNow(new Date(s.submitted_at), { addSuffix: true })}`}
                      </div>
                    </div>
                    <StatusBadge status={s.status as SubmissionStatus} />
                  </Link>
                </li>
              ))}
            </ul>
            {/* Pagination */}
            {data && data.last_page > 1 && (
              <div className="flex items-center justify-between px-6 py-3 border-t border-gray-100">
                <button disabled={page === 1} onClick={() => setPage(p => p - 1)} className="text-sm text-gray-500 hover:text-gray-700 disabled:opacity-40">Previous</button>
                <span className="text-xs text-gray-400">Page {page} of {data.last_page}</span>
                <button disabled={page === data.last_page} onClick={() => setPage(p => p + 1)} className="text-sm text-gray-500 hover:text-gray-700 disabled:opacity-40">Next</button>
              </div>
            )}
          </>
        )}
      </Card>
    </div>
  );
}
