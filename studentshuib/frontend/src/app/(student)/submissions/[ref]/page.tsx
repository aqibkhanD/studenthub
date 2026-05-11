'use client';
import { useState } from 'react';
import { useParams } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { studentApi } from '@/lib/api';
import { Card, CardBody, StatusBadge, Spinner, Button, Textarea, useToast } from '@/components/ui';
import type { Submission } from '@/types';
import { format } from 'date-fns';
import { ArrowLeft, Clock, FileText, MessageSquare, AlertCircle } from 'lucide-react';
import Link from 'next/link';

export default function SubmissionDetailPage() {
  const { ref }     = useParams<{ ref: string }>();
  const { toast }   = useToast();
  const qc          = useQueryClient();
  const [comment, setComment] = useState('');
  const [addingComment, setAddingComment] = useState(false);

  const { data: submission, isLoading } = useQuery({
    queryKey: ['submission', ref],
    queryFn:  () => studentApi.submission(ref),
    select:   (res) => res.data.submission as Submission,
  });

  const commentMut = useMutation({
    mutationFn: (body: string) => studentApi.addComment(ref, body),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['submission', ref] });
      setComment('');
      setAddingComment(false);
      toast('Comment added.', 'success');
    },
    onError: () => toast('Failed to add comment.', 'error'),
  });

  if (isLoading) return <div className="flex justify-center py-24"><Spinner /></div>;
  if (!submission) return <div className="p-6 text-gray-500">Submission not found.</div>;

  const isActionable = submission.status === 'returned' || submission.status === 'action_required';

  return (
    <div className="p-6 max-w-3xl mx-auto space-y-5">
      {/* Header */}
      <div className="flex items-start gap-3">
        <Link href="/submissions" className="text-gray-400 hover:text-gray-600 mt-1">
          <ArrowLeft className="w-5 h-5" />
        </Link>
        <div className="flex-1">
          <div className="flex items-center gap-3 flex-wrap">
            <h1 className="text-xl font-bold text-gray-900">{submission.form_type?.name}</h1>
            <StatusBadge status={submission.status} />
          </div>
          <div className="text-sm text-gray-400 mt-0.5">
            {submission.reference_no}
            {submission.submitted_at && ` · Submitted ${format(new Date(submission.submitted_at), 'dd MMM yyyy')}`}
          </div>
        </div>
      </div>

      {/* SLA warning */}
      {submission.sla_breached && (
        <div className="flex gap-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
          <AlertCircle className="w-4 h-4 mt-0.5 shrink-0" />
          This request has exceeded its expected processing time and has been escalated.
        </div>
      )}

      {/* Action required banner */}
      {isActionable && (
        <div className="p-4 bg-orange-50 border border-orange-200 rounded-xl">
          <p className="text-sm font-medium text-orange-800">Action required — please review the comments below and update your submission if needed.</p>
          <Link href={`/submissions/${ref}/resubmit`}>
            <Button size="sm" variant="danger" className="mt-3">Update Submission</Button>
          </Link>
        </div>
      )}

      {/* Details */}
      <Card>
        <div className="px-6 py-3 border-b border-gray-100 flex items-center gap-2 text-sm font-semibold text-gray-700">
          <FileText className="w-4 h-4" /> Request Details
        </div>
        <CardBody>
          <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div><dt className="text-gray-400">Department</dt><dd className="font-medium text-gray-900">{submission.department?.name}</dd></div>
            <div><dt className="text-gray-400">Category</dt><dd className="font-medium text-gray-900">{submission.form_type?.category?.replace(/_/g,' ')}</dd></div>
            {submission.sla_deadline && (
              <div><dt className="text-gray-400">Expected by</dt><dd className="font-medium text-gray-900">{format(new Date(submission.sla_deadline), 'dd MMM yyyy HH:mm')}</dd></div>
            )}
            <div><dt className="text-gray-400">Anonymous</dt><dd className="font-medium text-gray-900">{submission.is_anonymous ? 'Yes' : 'No'}</dd></div>
          </dl>
        </CardBody>
      </Card>

      {/* Status timeline */}
      {submission.status_history && submission.status_history.length > 0 && (
        <Card>
          <div className="px-6 py-3 border-b border-gray-100 flex items-center gap-2 text-sm font-semibold text-gray-700">
            <Clock className="w-4 h-4" /> Timeline
          </div>
          <CardBody>
            <ol className="space-y-4">
              {submission.status_history.map((h, i) => (
                <li key={h.id} className="flex gap-3">
                  <div className="flex flex-col items-center">
                    <div className={`w-3 h-3 rounded-full mt-0.5 ${i === 0 ? 'bg-brand-500' : 'bg-gray-200'}`} />
                    {i < submission.status_history!.length - 1 && <div className="w-px flex-1 bg-gray-100 my-1" />}
                  </div>
                  <div className="pb-2">
                    <div className="text-sm font-medium text-gray-900 capitalize">{h.to_status.replace(/_/g, ' ')}</div>
                    {h.comment && <p className="text-sm text-gray-600 mt-0.5">{h.comment}</p>}
                    <div className="text-xs text-gray-400 mt-1">{format(new Date(h.changed_at), 'dd MMM yyyy, HH:mm')}</div>
                  </div>
                </li>
              ))}
            </ol>
          </CardBody>
        </Card>
      )}

      {/* Documents */}
      {submission.documents && submission.documents.length > 0 && (
        <Card>
          <div className="px-6 py-3 border-b border-gray-100 text-sm font-semibold text-gray-700">Documents</div>
          <CardBody>
            <ul className="space-y-2">
              {submission.documents.map(d => (
                <li key={d.id} className="flex items-center justify-between p-2 rounded-lg bg-gray-50">
                  <div>
                    <div className="text-sm font-medium text-gray-900">{d.file_name}</div>
                    <div className="text-xs text-gray-400">{d.size_human}</div>
                  </div>
                  <a href={d.url} target="_blank" rel="noreferrer" className="text-xs text-brand-500 hover:underline">Download</a>
                </li>
              ))}
            </ul>
          </CardBody>
        </Card>
      )}

      {/* Comments */}
      <Card>
        <div className="px-6 py-3 border-b border-gray-100 flex items-center gap-2 text-sm font-semibold text-gray-700">
          <MessageSquare className="w-4 h-4" /> Comments
        </div>
        <CardBody>
          {submission.comments && submission.comments.length > 0 ? (
            <ul className="space-y-4 mb-4">
              {submission.comments.map(c => (
                <li key={c.id} className="text-sm">
                  <div className="flex items-center gap-2 mb-1">
                    <span className="font-medium text-gray-900">{c.user?.name ?? 'System'}</span>
                    <span className="text-xs text-gray-400">{format(new Date(c.created_at), 'dd MMM, HH:mm')}</span>
                  </div>
                  <p className="text-gray-700">{c.body}</p>
                </li>
              ))}
            </ul>
          ) : <p className="text-sm text-gray-400 mb-4">No comments yet.</p>}

          {addingComment ? (
            <div className="space-y-2">
              <Textarea placeholder="Write a comment..." value={comment} onChange={e => setComment(e.target.value)} rows={3} />
              <div className="flex gap-2">
                <Button size="sm" onClick={() => commentMut.mutate(comment)} loading={commentMut.isPending} disabled={!comment.trim()}>Post</Button>
                <Button size="sm" variant="ghost" onClick={() => setAddingComment(false)}>Cancel</Button>
              </div>
            </div>
          ) : (
            <Button size="sm" variant="outline" onClick={() => setAddingComment(true)}>Add comment</Button>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
