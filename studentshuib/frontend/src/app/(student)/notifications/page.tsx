'use client';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { studentApi } from '@/lib/api';
import { Card, Button, Spinner, EmptyState } from '@/components/ui';
import type { Notification, PaginatedResponse } from '@/types';
import { Bell, CheckCheck } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';
import Link from 'next/link';
import { clsx } from 'clsx';

export default function NotificationsPage() {
  const qc = useQueryClient();

  const { data, isLoading } = useQuery({
    queryKey: ['notifications'],
    queryFn:  () => studentApi.notifications(),
    select:   (res) => res.data as { notifications: PaginatedResponse<Notification>; unread_count: number },
  });

  const markAllMut = useMutation({
    mutationFn: () => studentApi.markAllRead(),
    onSuccess:  () => qc.invalidateQueries({ queryKey: ['notifications'] }),
  });

  const markReadMut = useMutation({
    mutationFn: (id: number) => studentApi.markRead(id),
    onSuccess:  () => qc.invalidateQueries({ queryKey: ['notifications'] }),
  });

  const notifications = data?.notifications.data ?? [];
  const unread        = data?.unread_count ?? 0;

  return (
    <div className="p-6 max-w-2xl mx-auto space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Notifications</h1>
          {unread > 0 && <p className="text-sm text-gray-500">{unread} unread</p>}
        </div>
        {unread > 0 && (
          <Button size="sm" variant="ghost" onClick={() => markAllMut.mutate()} loading={markAllMut.isPending}>
            <CheckCheck className="w-4 h-4" /> Mark all read
          </Button>
        )}
      </div>

      <Card>
        {isLoading ? (
          <div className="flex justify-center py-16"><Spinner /></div>
        ) : notifications.length === 0 ? (
          <EmptyState title="No notifications" description="You're all caught up." icon={Bell} />
        ) : (
          <ul className="divide-y divide-gray-50">
            {notifications.map((n) => (
              <li key={n.id}
                className={clsx('px-5 py-4 flex gap-4 transition-colors', !n.is_read && 'bg-blue-50/50')}
                onClick={() => !n.is_read && markReadMut.mutate(n.id)}
              >
                <div className={clsx('w-2 h-2 rounded-full mt-2 shrink-0', n.is_read ? 'bg-gray-200' : 'bg-brand-500')} />
                <div className="flex-1 min-w-0">
                  <div className="text-sm font-medium text-gray-900">{n.title}</div>
                  <p className="text-sm text-gray-600 mt-0.5">{n.body}</p>
                  <div className="flex items-center gap-3 mt-1">
                    <span className="text-xs text-gray-400">{formatDistanceToNow(new Date(n.created_at), { addSuffix: true })}</span>
                    {n.submission_reference_no && (
                      <Link href={`/submissions/${n.submission_reference_no}`} className="text-xs text-brand-500 hover:underline">
                        View request
                      </Link>
                    )}
                  </div>
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  );
}
