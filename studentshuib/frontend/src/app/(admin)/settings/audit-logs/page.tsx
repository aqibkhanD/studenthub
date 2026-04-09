'use client';
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { superApi } from '@/lib/api';
import { Card, Spinner, EmptyState } from '@/components/ui';
import type { AuditLog, PaginatedResponse } from '@/types';
import { ScrollText, Search } from 'lucide-react';
import { format } from 'date-fns';
import { clsx } from 'clsx';

const ACTION_COLOR: Record<string, string> = {
  created:       'bg-green-100 text-green-700',
  updated:       'bg-blue-100 text-blue-700',
  deleted:       'bg-red-100 text-red-700',
  status_change: 'bg-yellow-100 text-yellow-700',
  login:         'bg-gray-100 text-gray-600',
  logout:        'bg-gray-100 text-gray-600',
  assigned:      'bg-indigo-100 text-indigo-700',
  escalated:     'bg-orange-100 text-orange-700',
};

export default function AuditLogsPage() {
  const [search, setSearch] = useState('');
  const [page,   setPage]   = useState(1);

  const { data, isLoading } = useQuery({
    queryKey: ['audit-logs', search, page],
    queryFn:  () => superApi.auditLogs({ search: search || undefined, page }),
    select:   (res) => res.data as PaginatedResponse<AuditLog>,
  });

  const logs = data?.data ?? [];

  return (
    <div className="p-6 space-y-5">
      <h1 className="text-xl font-bold text-gray-900">Audit Logs</h1>

      <div className="relative w-72">
        <Search className="absolute left-3 top-2.5 w-4 h-4 text-gray-400" />
        <input
          className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
          placeholder="Search by user or entity..."
          value={search}
          onChange={e => { setSearch(e.target.value); setPage(1); }}
        />
      </div>

      <Card>
        {isLoading ? (
          <div className="flex justify-center py-16"><Spinner /></div>
        ) : logs.length === 0 ? (
          <EmptyState title="No audit logs" description="Actions will appear here." icon={ScrollText} />
        ) : (
          <>
            <div className="hidden md:grid grid-cols-12 gap-4 px-6 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
              <div className="col-span-2">Time</div>
              <div className="col-span-2">User</div>
              <div className="col-span-2">Action</div>
              <div className="col-span-2">Entity</div>
              <div className="col-span-4">Details</div>
            </div>
            <ul className="divide-y divide-gray-50">
              {logs.map((log) => (
                <li key={log.id} className="grid md:grid-cols-12 gap-4 px-6 py-3 items-start hover:bg-gray-50 transition-colors text-sm">
                  <div className="md:col-span-2 text-xs text-gray-400 mt-0.5 tabular-nums">
                    {format(new Date(log.created_at), 'dd MMM, HH:mm')}
                  </div>
                  <div className="md:col-span-2 text-gray-700 truncate">
                    {log.user?.name ?? <span className="italic text-gray-400">System</span>}
                  </div>
                  <div className="md:col-span-2">
                    <span className={clsx('text-xs font-medium px-1.5 py-0.5 rounded capitalize', ACTION_COLOR[log.action] ?? 'bg-gray-100 text-gray-600')}>
                      {log.action.replace(/_/g, ' ')}
                    </span>
                  </div>
                  <div className="md:col-span-2 text-gray-600">
                    <div className="font-medium capitalize">{log.entity_type?.replace('App\\Models\\', '')}</div>
                    {log.entity_id && <div className="text-xs text-gray-400 font-mono">#{log.entity_id}</div>}
                  </div>
                  <div className="md:col-span-4 text-xs text-gray-500">
                    {log.description ?? (
                      log.changes ? (
                        <pre className="whitespace-pre-wrap font-mono text-xs overflow-hidden max-h-16">
                          {JSON.stringify(log.changes, null, 2)}
                        </pre>
                      ) : '—'
                    )}
                  </div>
                </li>
              ))}
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
