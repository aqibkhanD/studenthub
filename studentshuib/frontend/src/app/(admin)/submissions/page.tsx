'use client';
import { useState, useEffect, Suspense } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useSearchParams } from 'next/navigation';
import { adminApi } from '@/lib/api';
import { Card, StatusBadge, Spinner, EmptyState, Select } from '@/components/ui';
import type { PaginatedResponse, Submission, SubmissionStatus } from '@/types';
import Link from 'next/link';
import { formatDistanceToNow } from 'date-fns';
import { Inbox, AlertCircle, Search, Download, CheckSquare, Square, X } from 'lucide-react';

// ---- Constants ----------------------------------------------------------------

const STATUS_OPTS = [
  { value: '', label: 'All statuses' },
  { value: 'submitted',       label: 'Submitted' },
  { value: 'routed',          label: 'Routed' },
  { value: 'in_review',       label: 'In Review' },
  { value: 'action_required', label: 'Action Required' },
  { value: 'escalated',       label: 'Escalated' },
  { value: 'approved',        label: 'Approved' },
  { value: 'rejected',        label: 'Rejected' },
  { value: 'returned',        label: 'Returned' },
  { value: 'completed',       label: 'Completed' },
];

const BULK_STATUS_OPTS = [
  { value: 'in_review',       label: 'Move to In Review' },
  { value: 'action_required', label: 'Requires Action' },
  { value: 'approved',        label: 'Approve' },
  { value: 'rejected',        label: 'Reject' },
  { value: 'returned',        label: 'Return to Student' },
  { value: 'completed',       label: 'Mark Completed' },
];

// ---- Page ---------------------------------------------------------------
// Note: AdminInboxContent uses useSearchParams so it must live inside a
// Suspense boundary. The default export wraps it appropriately.

function AdminInboxContent() {
  const searchParams  = useSearchParams();
  const queryClient   = useQueryClient();

  // Filters
  const [status, setStatus]   = useState(searchParams.get('status') ?? '');
  const [search, setSearch]   = useState('');
  const [slaBreached, setSla] = useState(searchParams.get('sla_breached') === '1');
  const [page, setPage]       = useState(1);

  // Bulk selection
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [bulkStatus, setBulkStatus] = useState('');
  const [bulkComment, setBulkComment] = useState('');
  const [showBulkComment, setShowBulkComment] = useState(false);
  const [bulkError, setBulkError] = useState('');

  // CSV export loading
  const [exporting, setExporting] = useState(false);

  useEffect(() => { setPage(1); setSelected(new Set()); }, [status, search, slaBreached]);

  const { data, isLoading } = useQuery({
    queryKey: ['admin-submissions', status, search, slaBreached, page],
    queryFn:  () => adminApi.submissions({
      status:      status || undefined,
      search:      search || undefined,
      sla_breached:slaBreached ? 1 : undefined,
      page,
    }),
    select: (res) => res.data as PaginatedResponse<Submission>,
  });

  const submissions = data?.data ?? [];

  // ---- Bulk action mutation ------------------------------------------------

  const bulkMutation = useMutation({
    mutationFn: ({ refNos, status, comment }: { refNos: string[]; status: string; comment?: string }) =>
      adminApi.bulkStatus(refNos, status, comment),
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['admin-submissions'] });
      setSelected(new Set());
      setBulkStatus('');
      setBulkComment('');
      setShowBulkComment(false);
      setBulkError('');
      // Show brief success note via the response message (could use toast here)
    },
    onError: (err: unknown) => {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        ?? 'Bulk update failed.';
      setBulkError(msg);
    },
  });

  const handleBulkApply = () => {
    setBulkError('');
    if (!bulkStatus) { setBulkError('Please select a target status.'); return; }
    const needsComment = ['rejected', 'returned'].includes(bulkStatus);
    if (needsComment && !bulkComment.trim()) {
      setBulkError('A comment is required when rejecting or returning submissions.');
      return;
    }
    bulkMutation.mutate({
      refNos:  Array.from(selected),
      status:  bulkStatus,
      comment: bulkComment.trim() || undefined,
    });
  };

  // ---- Checkbox helpers ----------------------------------------------------

  const toggleAll = () => {
    if (selected.size === submissions.length && submissions.length > 0) {
      setSelected(new Set());
    } else {
      setSelected(new Set(submissions.map((s) => s.reference_no)));
    }
  };

  const toggleOne = (ref: string) => {
    setSelected((prev) => {
      const next = new Set(prev);
      next.has(ref) ? next.delete(ref) : next.add(ref);
      return next;
    });
  };

  const allSelected = submissions.length > 0 && selected.size === submissions.length;
  const someSelected = selected.size > 0 && !allSelected;

  // ---- CSV Export ----------------------------------------------------------

  const handleExport = async () => {
    setExporting(true);
    try {
      const res = await adminApi.exportCsv({
        status:      status || undefined,
        search:      search || undefined,
        sla_breached:slaBreached ? 1 : undefined,
      });
      // Create blob download
      const url  = window.URL.createObjectURL(new Blob([res.data], { type: 'text/csv;charset=utf-8;' }));
      const link = document.createElement('a');
      link.href  = url;
      link.setAttribute('download', `submissions_${new Date().toISOString().slice(0, 10)}.csv`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch {
      // silent — could add toast here
    } finally {
      setExporting(false);
    }
  };

  // ---- Render ---------------------------------------------------------------

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-center justify-between flex-wrap gap-3">
        <h1 className="text-xl font-bold text-gray-900">Submissions Inbox</h1>
        <button
          onClick={handleExport}
          disabled={exporting}
          className="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors"
        >
          {exporting ? <Spinner className="w-4 h-4" /> : <Download className="w-4 h-4" />}
          Export CSV
        </button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3">
        <div className="relative w-64">
          <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
          <input
            className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
            placeholder="Search ref. or student name..."
            value={search}
            onChange={e => { setSearch(e.target.value); }}
          />
        </div>
        <div className="w-44">
          <Select options={STATUS_OPTS} value={status} onChange={e => setStatus(e.target.value)} />
        </div>
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
          <input type="checkbox" className="accent-red-600" checked={slaBreached} onChange={e => setSla(e.target.checked)} />
          <AlertCircle className="w-4 h-4 text-red-500" />
          SLA breached only
        </label>
      </div>

      {/* Bulk action bar — visible when items selected */}
      {selected.size > 0 && (
        <div className="flex flex-wrap items-center gap-3 bg-brand-50 border border-brand-200 rounded-xl px-4 py-3">
          <span className="text-sm font-semibold text-brand-700">
            {selected.size} selected
          </span>
          <button
            onClick={() => { setSelected(new Set()); setBulkError(''); }}
            className="text-brand-400 hover:text-brand-700"
          >
            <X className="w-4 h-4" />
          </button>

          <div className="flex items-center gap-2 ml-auto flex-wrap">
            <select
              value={bulkStatus}
              onChange={e => {
                setBulkStatus(e.target.value);
                setShowBulkComment(['rejected', 'returned'].includes(e.target.value));
                setBulkError('');
              }}
              className="px-3 py-1.5 text-sm border border-brand-200 rounded-lg bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500"
            >
              <option value="">Change status to...</option>
              {BULK_STATUS_OPTS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>

            {showBulkComment && (
              <input
                value={bulkComment}
                onChange={e => setBulkComment(e.target.value)}
                placeholder="Comment (required for reject/return)"
                className="px-3 py-1.5 text-sm border border-brand-200 rounded-lg bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-brand-500 w-72"
              />
            )}

            <button
              onClick={handleBulkApply}
              disabled={bulkMutation.isPending || !bulkStatus}
              className="px-4 py-1.5 bg-brand-500 text-white text-sm font-medium rounded-lg hover:bg-brand-600 disabled:opacity-50 transition-colors inline-flex items-center gap-2"
            >
              {bulkMutation.isPending ? <Spinner className="w-4 h-4" /> : null}
              Apply
            </button>
          </div>

          {bulkError && (
            <p className="w-full text-xs text-red-600 mt-1">{bulkError}</p>
          )}
          {bulkMutation.isSuccess && (
            <p className="w-full text-xs text-green-700 mt-1">
              {(bulkMutation.data as { data?: { message?: string } })?.data?.message ?? 'Done.'}
            </p>
          )}
        </div>
      )}

      <Card>
        {isLoading ? (
          <div className="flex justify-center py-16"><Spinner /></div>
        ) : submissions.length === 0 ? (
          <EmptyState title="No submissions" description="Nothing matches your filters." icon={Inbox} />
        ) : (
          <>
            {/* Table header with "select all" checkbox */}
            <div className="hidden md:grid grid-cols-12 gap-4 px-4 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 items-center">
              <div className="col-span-1 flex items-center justify-center">
                <button onClick={toggleAll} className="text-gray-400 hover:text-gray-700">
                  {allSelected
                    ? <CheckSquare className="w-4 h-4 text-brand-500" />
                    : someSelected
                    ? <CheckSquare className="w-4 h-4 text-gray-300" />
                    : <Square className="w-4 h-4" />}
                </button>
              </div>
              <div className="col-span-3">Reference</div>
              <div className="col-span-3">Form Type</div>
              <div className="col-span-2">Student</div>
              <div className="col-span-1">Dept</div>
              <div className="col-span-2">Status</div>
            </div>

            <ul className="divide-y divide-gray-50">
              {submissions.map((s) => {
                const isChecked = selected.has(s.reference_no);
                return (
                  <li key={s.id} className={`group ${isChecked ? 'bg-brand-50/50' : ''}`}>
                    <div className="grid md:grid-cols-12 gap-4 px-4 py-4 items-center hover:bg-gray-50 transition-colors">
                      {/* Checkbox */}
                      <div className="hidden md:flex col-span-1 items-center justify-center">
                        <button
                          onClick={() => toggleOne(s.reference_no)}
                          className="text-gray-300 hover:text-brand-500 transition-colors"
                        >
                          {isChecked
                            ? <CheckSquare className="w-4 h-4 text-brand-500" />
                            : <Square className="w-4 h-4" />}
                        </button>
                      </div>

                      {/* Row content — clicking the content area navigates */}
                      <Link
                        href={`/admin/submissions/${s.reference_no}`}
                        className="contents"
                      >
                        <div className="md:col-span-3">
                          <div className="text-sm font-mono font-medium text-gray-900">{s.reference_no}</div>
                          <div className="text-xs text-gray-400 flex items-center gap-1 mt-0.5">
                            {s.sla_breached && <AlertCircle className="w-3 h-3 text-red-500" />}
                            {s.submitted_at ? formatDistanceToNow(new Date(s.submitted_at), { addSuffix: true }) : 'Draft'}
                          </div>
                        </div>
                        <div className="md:col-span-3 text-sm text-gray-700">{s.form_type?.name}</div>
                        <div className="md:col-span-2 text-sm text-gray-600">
                          {s.is_anonymous ? <span className="italic text-gray-400">Anonymous</span> : s.student?.name ?? '—'}
                        </div>
                        <div className="md:col-span-1 text-xs text-gray-500 truncate" title={s.department?.name}>
                          {s.department?.name}
                        </div>
                        <div className="md:col-span-2">
                          <StatusBadge status={s.status as SubmissionStatus} />
                        </div>
                      </Link>
                    </div>
                  </li>
                );
              })}
            </ul>

            {data && data.last_page > 1 && (
              <div className="flex items-center justify-between px-6 py-3 border-t border-gray-100">
                <button disabled={page === 1} onClick={() => setPage(p => p - 1)} className="text-sm text-gray-500 hover:text-gray-700 disabled:opacity-40">Previous</button>
                <span className="text-xs text-gray-400">Page {page} of {data.last_page} · {data.total} total</span>
                <button disabled={page === data.last_page} onClick={() => setPage(p => p + 1)} className="text-sm text-gray-500 hover:text-gray-700 disabled:opacity-40">Next</button>
              </div>
            )}
          </>
        )}
      </Card>
    </div>
  );
}

// Suspense boundary required by Next.js App Router for useSearchParams
export default function AdminInboxPage() {
  return (
    <Suspense fallback={<div className="flex justify-center py-24"><Spinner /></div>}>
      <AdminInboxContent />
    </Suspense>
  );
}
