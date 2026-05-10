'use client';
import { useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { adminApi } from '@/lib/api';
import {
  Card, CardBody, Button, Spinner, StatusBadge, useToast,
} from '@/components/ui';
import type { Submission, SubmissionStatus, User } from '@/types';
import { formatDistanceToNow, format } from 'date-fns';
import {
  ArrowLeft, AlertCircle, Clock, User as UserIcon, Building2,
  FileText, MessageSquare, CheckCircle, XCircle, RotateCcw,
  Paperclip, Send, Shield, ChevronDown, ChevronUp,
} from 'lucide-react';
import Link from 'next/link';
import { clsx } from 'clsx';

/* ------------------------------------------------------------------ */
/* Helpers                                                              */
/* ------------------------------------------------------------------ */
function Section({ title, icon: Icon, children, className }: {
  title: string; icon: React.ElementType; children: React.ReactNode; className?: string;
}) {
  return (
    <Card className={className}>
      <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
        <Icon className="w-4 h-4 text-gray-400" />
        <h2 className="text-sm font-semibold text-gray-900">{title}</h2>
      </div>
      <CardBody>{children}</CardBody>
    </Card>
  );
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex flex-col sm:flex-row sm:gap-4 py-2 border-b border-gray-50 last:border-0">
      <span className="text-xs font-medium text-gray-400 uppercase tracking-wide sm:w-36 shrink-0">{label}</span>
      <span className="text-sm text-gray-800 mt-0.5 sm:mt-0">{value ?? '—'}</span>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Action Modal                                                         */
/* ------------------------------------------------------------------ */
type ActionType = 'approve' | 'reject' | 'return' | 'in_review' | 'escalate';

function ActionModal({
  action, onClose, onSubmit, loading,
}: {
  action: ActionType; onClose: () => void; onSubmit: (comment: string) => void; loading: boolean;
}) {
  const [comment, setComment] = useState('');

  const config: Record<ActionType, { label: string; color: string; requireComment: boolean }> = {
    approve:   { label: 'Approve',        color: 'bg-green-600 hover:bg-green-700', requireComment: false },
    reject:    { label: 'Reject',         color: 'bg-red-600 hover:bg-red-700',     requireComment: true  },
    return:    { label: 'Return',         color: 'bg-orange-600 hover:bg-orange-700', requireComment: true },
    in_review: { label: 'Mark In Review', color: 'bg-yellow-600 hover:bg-yellow-700', requireComment: false },
    escalate:  { label: 'Escalate',       color: 'bg-purple-600 hover:bg-purple-700', requireComment: true },
  };

  const cfg = config[action];
  const canSubmit = !cfg.requireComment || comment.trim().length > 0;

  return (
    <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 px-4">
      <div className="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 space-y-4">
        <h3 className="text-base font-semibold text-gray-900">{cfg.label} Submission</h3>
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">
            Comment {cfg.requireComment && <span className="text-red-500">*</span>}
          </label>
          <textarea
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-brand-500"
            rows={4}
            placeholder={cfg.requireComment ? 'Required — explain the decision to the student...' : 'Optional note...'}
            value={comment}
            onChange={e => setComment(e.target.value)}
          />
        </div>
        <div className="flex gap-2 justify-end">
          <button onClick={onClose} className="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
          <button
            disabled={!canSubmit || loading}
            onClick={() => onSubmit(comment)}
            className={clsx('px-4 py-2 text-sm text-white rounded-lg font-medium disabled:opacity-50', cfg.color)}
          >
            {loading ? 'Saving...' : cfg.label}
          </button>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------ */
/* Main Page                                                            */
/* ------------------------------------------------------------------ */
export default function AdminSubmissionDetailPage() {
  const { ref } = useParams<{ ref: string }>();
  const router  = useRouter();
  const qc      = useQueryClient();
  const { toast } = useToast();

  const [activeAction, setActiveAction] = useState<ActionType | null>(null);
  const [newComment,   setNewComment]   = useState('');
  const [commentType,  setCommentType]  = useState<'internal' | 'external'>('external');
  const [showTimeline, setShowTimeline] = useState(true);
  const [uploadFile,   setUploadFile]   = useState<File | null>(null);

  /* Fetch submission */
  const { data: submission, isLoading } = useQuery({
    queryKey: ['admin-submission', ref],
    queryFn:  () => adminApi.getSubmission(ref),
    select:   (res) => res.data as Submission,
  });

  /* Fetch staff list for assignment */
  const { data: staffList } = useQuery({
    queryKey: ['admin-staff'],
    queryFn:  () => adminApi.staff(),
    select:   (res) => res.data as User[],
    enabled:  !!submission,
  });

  /* Status update */
  const statusMut = useMutation({
    mutationFn: ({ status, comment }: { status: string; comment: string }) =>
      adminApi.updateStatus(ref, status, comment),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-submission', ref] });
      qc.invalidateQueries({ queryKey: ['admin-submissions'] });
      setActiveAction(null);
      toast({ title: 'Status updated', variant: 'success' });
    },
    onError: () => toast({ title: 'Failed to update status', variant: 'error' }),
  });

  /* Add comment */
  const commentMut = useMutation({
    mutationFn: () => adminApi.addComment(ref, newComment, commentType),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-submission', ref] });
      setNewComment('');
      toast({ title: 'Comment added', variant: 'success' });
    },
    onError: () => toast({ title: 'Failed to add comment', variant: 'error' }),
  });

  /* Assign */
  const assignMut = useMutation({
    mutationFn: (userId: number) => adminApi.assign(ref, userId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-submission', ref] });
      toast({ title: 'Submission assigned', variant: 'success' });
    },
    onError: () => toast({ title: 'Failed to assign', variant: 'error' }),
  });

  /* Upload document */
  const uploadMut = useMutation({
    mutationFn: () => {
      const fd = new FormData();
      fd.append('document', uploadFile!);
      fd.append('source', 'admin');
      return adminApi.uploadDocument(ref, fd);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['admin-submission', ref] });
      setUploadFile(null);
      toast({ title: 'Document uploaded', variant: 'success' });
    },
    onError: () => toast({ title: 'Upload failed', variant: 'error' }),
  });

  /* Action handler */
  const handleAction = (comment: string) => {
    if (!activeAction) return;
    const statusMap: Record<ActionType, string> = {
      approve:   'approved',
      reject:    'rejected',
      return:    'returned',
      in_review: 'in_review',
      escalate:  'escalated',
    };
    statusMut.mutate({ status: statusMap[activeAction], comment });
  };

  if (isLoading) return <div className="flex justify-center py-24"><Spinner /></div>;
  if (!submission) return (
    <div className="p-6">
      <p className="text-gray-500">Submission not found.</p>
      <Link href="/admin/submissions" className="text-brand-500 text-sm mt-2 inline-block">Back to inbox</Link>
    </div>
  );

  const isOpen = !['approved', 'rejected', 'completed', 'cancelled'].includes(submission.status);
  const slaBreached = submission.sla_breached;

  /* Which actions are available depends on current status */
  const availableActions: ActionType[] = (() => {
    const s = submission.status as string;
    if (s === 'submitted' || s === 'routed')    return ['in_review', 'approve', 'reject', 'return'];
    if (s === 'in_review')                       return ['approve', 'reject', 'return', 'escalate'];
    if (s === 'action_required')                 return ['in_review', 'approve', 'reject'];
    if (s === 'escalated')                       return ['approve', 'reject', 'return'];
    if (s === 'returned')                        return ['in_review'];
    return [];
  })();

  const actionLabels: Record<ActionType, { label: string; Icon: React.ElementType; style: string }> = {
    in_review: { label: 'In Review',  Icon: Clock,        style: 'border-yellow-300 text-yellow-700 hover:bg-yellow-50' },
    approve:   { label: 'Approve',    Icon: CheckCircle,  style: 'border-green-300 text-green-700 hover:bg-green-50'   },
    reject:    { label: 'Reject',     Icon: XCircle,      style: 'border-red-300 text-red-700 hover:bg-red-50'          },
    return:    { label: 'Return',     Icon: RotateCcw,    style: 'border-orange-300 text-orange-700 hover:bg-orange-50' },
    escalate:  { label: 'Escalate',   Icon: Shield,       style: 'border-purple-300 text-purple-700 hover:bg-purple-50' },
  };

  return (
    <div className="p-6 max-w-5xl mx-auto space-y-5">
      {/* Header */}
      <div className="flex items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <button onClick={() => router.back()} className="text-gray-400 hover:text-gray-700">
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-lg font-bold text-gray-900 font-mono">{submission.reference_no}</h1>
              <StatusBadge status={submission.status as SubmissionStatus} />
              {slaBreached && (
                <span className="inline-flex items-center gap-1 text-xs font-medium text-red-700 bg-red-100 px-2 py-0.5 rounded-full">
                  <AlertCircle className="w-3 h-3" /> SLA Breached
                </span>
              )}
            </div>
            <p className="text-sm text-gray-500 mt-0.5">
              {submission.form_type?.name} · {submission.department?.name}
            </p>
          </div>
        </div>

        {/* Action buttons */}
        {isOpen && availableActions.length > 0 && (
          <div className="flex flex-wrap gap-2 shrink-0">
            {availableActions.map((a) => {
              const { label, Icon, style } = actionLabels[a];
              return (
                <button
                  key={a}
                  onClick={() => setActiveAction(a)}
                  className={clsx('inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors', style)}
                >
                  <Icon className="w-3.5 h-3.5" />{label}
                </button>
              );
            })}
          </div>
        )}
      </div>

      <div className="grid lg:grid-cols-3 gap-5">
        {/* Left column — main info */}
        <div className="lg:col-span-2 space-y-5">

          {/* Submission details */}
          <Section title="Submission Details" icon={FileText}>
            <InfoRow label="Reference"     value={<span className="font-mono">{submission.reference_no}</span>} />
            <InfoRow label="Form Type"     value={submission.form_type?.name} />
            <InfoRow label="Category"      value={submission.form_type?.category} />
            <InfoRow label="Department"    value={submission.department?.name} />
            <InfoRow label="Submitted"     value={submission.submitted_at ? format(new Date(submission.submitted_at), 'dd MMM yyyy, HH:mm') : 'Draft'} />
            <InfoRow label="SLA Deadline"  value={
              submission.sla_deadline ? (
                <span className={clsx('font-medium', slaBreached ? 'text-red-600' : 'text-gray-800')}>
                  {format(new Date(submission.sla_deadline), 'dd MMM yyyy, HH:mm')}
                  {slaBreached ? ' (BREACHED)' : ' (' + formatDistanceToNow(new Date(submission.sla_deadline), { addSuffix: true }) + ')'}
                </span>
              ) : '—'
            } />
            <InfoRow label="Assigned To"   value={submission.assigned_to ? (
              <div className="flex items-center gap-2">
                <span>{submission.assigned_to.name}</span>
                {staffList && (
                  <select
                    className="ml-2 text-xs border border-gray-200 rounded px-1 py-0.5"
                    value={submission.assigned_to.id}
                    onChange={e => assignMut.mutate(Number(e.target.value))}
                  >
                    {staffList.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                  </select>
                )}
              </div>
            ) : (
              staffList && staffList.length > 0 ? (
                <select
                  className="text-xs border border-gray-300 rounded px-2 py-1"
                  defaultValue=""
                  onChange={e => e.target.value && assignMut.mutate(Number(e.target.value))}
                >
                  <option value="">Assign to...</option>
                  {staffList.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                </select>
              ) : 'Unassigned'
            )} />
          </Section>

          {/* Form data */}
          {submission.form_data && Object.keys(submission.form_data).length > 0 && (
            <Section title="Form Data" icon={FileText}>
              {Object.entries(submission.form_data).map(([k, v]) => (
                <InfoRow key={k} label={k.replace(/_/g, ' ')} value={String(v)} />
              ))}
            </Section>
          )}

          {/* Documents */}
          <Section title="Documents" icon={Paperclip}>
            {submission.documents && submission.documents.length > 0 ? (
              <ul className="space-y-2">
                {submission.documents.map((doc) => (
                  <li key={doc.id} className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                    <Paperclip className="w-4 h-4 text-gray-400 shrink-0" />
                    <div className="flex-1 min-w-0">
                      <div className="text-sm font-medium text-gray-800 truncate">{doc.original_name}</div>
                      <div className="text-xs text-gray-400">{doc.size_human} · {doc.source === 'admin' ? 'Staff upload' : 'Student upload'}</div>
                    </div>
                    <a
                      href={doc.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-xs text-brand-500 hover:underline shrink-0"
                    >View</a>
                  </li>
                ))}
              </ul>
            ) : <p className="text-sm text-gray-400">No documents attached.</p>}

            {/* Admin upload */}
            <div className="mt-4 pt-4 border-t border-gray-100">
              <p className="text-xs font-medium text-gray-500 mb-2">Attach admin document</p>
              <div className="flex gap-2">
                <input
                  type="file"
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                  className="text-xs text-gray-600 file:mr-2 file:py-1 file:px-2 file:rounded file:border file:border-gray-300 file:text-xs file:text-gray-700 file:bg-white hover:file:bg-gray-50"
                  onChange={e => setUploadFile(e.target.files?.[0] ?? null)}
                />
                {uploadFile && (
                  <Button size="sm" onClick={() => uploadMut.mutate()} loading={uploadMut.isPending}>
                    Upload
                  </Button>
                )}
              </div>
            </div>
          </Section>

          {/* Comments */}
          <Section title="Comments" icon={MessageSquare}>
            {submission.comments && submission.comments.length > 0 ? (
              <ul className="space-y-4 mb-5">
                {submission.comments.map((c) => (
                  <li key={c.id} className={clsx('flex gap-3', c.is_internal && 'opacity-75')}>
                    <div className="w-7 h-7 rounded-full bg-gray-200 flex items-center justify-center shrink-0 mt-0.5">
                      <UserIcon className="w-3.5 h-3.5 text-gray-500" />
                    </div>
                    <div className="flex-1">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-sm font-medium text-gray-800">{c.user?.name ?? 'System'}</span>
                        {c.is_internal && (
                          <span className="text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded font-medium">Internal</span>
                        )}
                        <span className="text-xs text-gray-400">{formatDistanceToNow(new Date(c.created_at), { addSuffix: true })}</span>
                      </div>
                      <p className="text-sm text-gray-700 mt-0.5 whitespace-pre-wrap">{c.body}</p>
                    </div>
                  </li>
                ))}
              </ul>
            ) : <p className="text-sm text-gray-400 mb-4">No comments yet.</p>}

            {/* New comment */}
            <div className="space-y-2">
              <div className="flex gap-3">
                <button
                  className={clsx('text-xs px-2 py-1 rounded border transition-colors',
                    commentType === 'external' ? 'bg-brand-50 border-brand-300 text-brand-700' : 'border-gray-200 text-gray-500 hover:border-gray-300')}
                  onClick={() => setCommentType('external')}
                >Visible to student</button>
                <button
                  className={clsx('text-xs px-2 py-1 rounded border transition-colors',
                    commentType === 'internal' ? 'bg-yellow-50 border-yellow-300 text-yellow-700' : 'border-gray-200 text-gray-500 hover:border-gray-300')}
                  onClick={() => setCommentType('internal')}
                >Internal note</button>
              </div>
              <div className="flex gap-2">
                <textarea
                  rows={2}
                  className="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-brand-500"
                  placeholder={commentType === 'internal' ? 'Internal note (not visible to student)...' : 'Write a comment visible to the student...'}
                  value={newComment}
                  onChange={e => setNewComment(e.target.value)}
                />
                <button
                  disabled={!newComment.trim() || commentMut.isPending}
                  onClick={() => commentMut.mutate()}
                  className="px-3 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 disabled:opacity-40 self-end"
                >
                  <Send className="w-4 h-4" />
                </button>
              </div>
            </div>
          </Section>
        </div>

        {/* Right column — student info + timeline */}
        <div className="space-y-5">

          {/* Student info */}
          <Section title="Student" icon={UserIcon}>
            {submission.is_anonymous ? (
              <p className="text-sm text-gray-400 italic">Anonymous submission</p>
            ) : submission.student ? (
              <>
                <InfoRow label="Name"    value={submission.student.name} />
                <InfoRow label="Email"   value={submission.student.email} />
                <InfoRow label="Phone"   value={submission.student.phone ?? '—'} />
                <InfoRow label="Dept"    value={submission.student.department?.name} />
              </>
            ) : <p className="text-sm text-gray-400">No student data.</p>}
          </Section>

          {/* Status timeline */}
          <Card>
            <button
              className="w-full px-6 py-4 border-b border-gray-100 flex items-center justify-between"
              onClick={() => setShowTimeline(v => !v)}
            >
              <div className="flex items-center gap-2">
                <Clock className="w-4 h-4 text-gray-400" />
                <span className="text-sm font-semibold text-gray-900">Status History</span>
              </div>
              {showTimeline ? <ChevronUp className="w-4 h-4 text-gray-400" /> : <ChevronDown className="w-4 h-4 text-gray-400" />}
            </button>

            {showTimeline && (
              <CardBody>
                {submission.status_history && submission.status_history.length > 0 ? (
                  <ol className="relative border-l border-gray-200 ml-2 space-y-5">
                    {[...submission.status_history].reverse().map((h, i) => (
                      <li key={h.id} className="ml-4">
                        <div className={clsx(
                          'absolute w-3 h-3 rounded-full -left-1.5 border border-white',
                          i === 0 ? 'bg-brand-500' : 'bg-gray-300'
                        )} />
                        <div className="text-xs text-gray-400 mb-0.5">
                          {format(new Date(h.created_at), 'dd MMM, HH:mm')}
                          {h.changed_by && <span className="ml-1">· {h.changed_by.name}</span>}
                        </div>
                        <div className="text-sm font-medium text-gray-800 capitalize">
                          {h.new_status.replace(/_/g, ' ')}
                        </div>
                        {h.comment && (
                          <p className="text-xs text-gray-500 mt-0.5 line-clamp-2">{h.comment}</p>
                        )}
                      </li>
                    ))}
                  </ol>
                ) : <p className="text-sm text-gray-400">No history yet.</p>}
              </CardBody>
            )}
          </Card>

          {/* Approval records */}
          {submission.approval_records && submission.approval_records.length > 0 && (
            <Section title="Approvals" icon={Shield}>
              <ul className="space-y-3">
                {submission.approval_records.map((a) => (
                  <li key={a.id} className="flex items-start gap-2 text-sm">
                    <div className={clsx('w-4 h-4 rounded-full mt-0.5 shrink-0 flex items-center justify-center',
                      a.action === 'approved' ? 'bg-green-500' : a.action === 'rejected' ? 'bg-red-500' : 'bg-orange-500'
                    )}>
                      {a.action === 'approved'
                        ? <CheckCircle className="w-3 h-3 text-white" />
                        : <XCircle className="w-3 h-3 text-white" />
                      }
                    </div>
                    <div>
                      <div className="font-medium text-gray-800">{a.approver?.name}</div>
                      <div className="text-xs text-gray-500 capitalize">{a.action} · Step {a.step_order}</div>
                      {a.comment && <p className="text-xs text-gray-400 mt-0.5">{a.comment}</p>}
                    </div>
                  </li>
                ))}
              </ul>
            </Section>
          )}
        </div>
      </div>

      {/* Action modal */}
      {activeAction && (
        <ActionModal
          action={activeAction}
          onClose={() => setActiveAction(null)}
          onSubmit={handleAction}
          loading={statusMut.isPending}
        />
      )}
    </div>
  );
}
