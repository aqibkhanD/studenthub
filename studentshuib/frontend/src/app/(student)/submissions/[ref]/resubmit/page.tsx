'use client';
import { useEffect, useRef, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { studentApi } from '@/lib/api';
import {
  Button, Card, CardBody, Input, Textarea, Select, Spinner, useToast,
} from '@/components/ui';
import type { FormType, Submission } from '@/types';
import { ArrowLeft, Paperclip, RotateCcw, FileText, CheckCircle } from 'lucide-react';
import Link from 'next/link';
import { format } from 'date-fns';

// ----------------------------------------------------------------
// Resubmit page — only reachable for submissions in
// `returned` / `action_required` / `draft` status. Mirrors the
// structure of forms/[slug]/page.tsx but pre-populates form_data
// from the existing submission and uses PUT instead of POST.
// ----------------------------------------------------------------

export default function ResubmitPage() {
  const { ref }   = useParams<{ ref: string }>();
  const router    = useRouter();
  const { toast } = useToast();

  const [submitState, setSubmitState]       = useState<'idle' | 'submitting' | 'uploading' | 'done'>('idle');
  const [uploadProgress, setUploadProgress] = useState('');

  // File inputs are managed outside react-hook-form
  const fileRefs = useRef<Record<string, HTMLInputElement | null>>({});

  // ── Fetch existing submission ────────────────────────────────────
  const { data: submission, isLoading: subLoading } = useQuery({
    queryKey: ['submission', ref],
    queryFn:  () => studentApi.submission(ref),
    select:   (res) => res.data.submission as Submission,
  });

  // ── Fetch full form-type schema (with fields) via slug ──────────
  // The submission's form_type subset only includes id/name/slug/category;
  // we need the full record (with fields[]) to render the form.
  const slug = submission?.form_type?.slug;
  const { data: formType, isLoading: ftLoading } = useQuery({
    queryKey: ['form-type', slug],
    queryFn:  () => studentApi.formType(slug!),
    select:   (res) => res.data.form_type as FormType,
    enabled:  !!slug,
  });

  const { register, handleSubmit, reset, formState: { errors } } = useForm<Record<string, string>>();

  // Pre-populate fields once the submission has loaded
  useEffect(() => {
    if (!submission?.form_data) return;
    const defaults: Record<string, string> = {};
    Object.entries(submission.form_data).forEach(([k, v]) => {
      defaults[k] = v == null ? '' : String(v);
    });
    reset(defaults);
  }, [submission, reset]);

  // Status guard — bounce out if this submission isn't editable
  useEffect(() => {
    if (!submission) return;
    const editable: Submission['status'][] = ['returned', 'action_required', 'draft'];
    if (!editable.includes(submission.status)) {
      toast({ title: 'This submission cannot be edited from its current status.', variant: 'error' });
      router.replace(`/submissions/${ref}`);
    }
  }, [submission, ref, router, toast]);

  if (subLoading || ftLoading) {
    return <div className="flex justify-center py-24"><Spinner /></div>;
  }
  if (!submission || !formType) {
    return <div className="p-6 text-gray-500">Could not load submission.</div>;
  }

  // Most recent return / action-required entry (for context message)
  const latestReturnEntry = submission.status_history?.find(h => {
    const s = h.new_status ?? h.to_status;
    return s === 'returned' || s === 'action_required';
  });

  const onSubmit = async (formData: Record<string, string>) => {
    setSubmitState('submitting');

    const fileFields = (formType.fields ?? []).filter(f => f.field_type === 'file' && f.is_active);
    const textFields = (formType.fields ?? []).filter(f => f.field_type !== 'file');

    const textData: Record<string, string> = {};
    textFields.forEach(f => {
      if (formData[f.field_key] !== undefined) textData[f.field_key] = formData[f.field_key];
    });
    // If no dynamic fields, send the raw formData (fallback "description" field)
    const payload = (formType.fields?.length ?? 0) === 0 ? formData : textData;

    try {
      await studentApi.resubmit(ref, { form_data: payload, submit: true });

      // Upload any newly attached files (existing docs are kept by the backend)
      const filesToUpload = fileFields.filter(f => fileRefs.current[f.field_key]?.files?.[0]);
      if (filesToUpload.length > 0) {
        setSubmitState('uploading');
        let uploaded = 0;
        for (const field of filesToUpload) {
          const file = fileRefs.current[field.field_key]?.files?.[0];
          if (!file) continue;
          uploaded++;
          setUploadProgress(`Uploading ${field.label} (${uploaded}/${filesToUpload.length})…`);

          const fd = new FormData();
          fd.append('document', file);
          fd.append('description', field.label);
          try {
            await studentApi.uploadDocument(ref, fd);
          } catch {
            toast({
              title: `Could not upload "${field.label}". You can attach it later from the submission page.`,
              variant: 'error',
            });
          }
        }
      }

      setSubmitState('done');
      toast({ title: 'Submission updated and resent.', variant: 'success' });
      router.push(`/submissions/${ref}`);
    } catch (err: unknown) {
      setSubmitState('idle');
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast({ title: msg || 'Resubmit failed. Please try again.', variant: 'error' });
    }
  };

  const isBusy     = submitState === 'submitting' || submitState === 'uploading';
  const fileFields = (formType.fields ?? []).filter(f => f.field_type === 'file' && f.is_active);
  const hasFiles   = fileFields.length > 0;

  return (
    <div className="p-6 max-w-2xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center gap-3">
        <Link href={`/submissions/${ref}`} className="text-gray-400 hover:text-gray-600">
          <ArrowLeft className="w-5 h-5" />
        </Link>
        <div>
          <h1 className="text-xl font-bold text-gray-900">Update & Resubmit</h1>
          <p className="text-sm text-gray-500">
            <span className="font-mono">{submission.reference_no}</span> · {formType.name}
          </p>
        </div>
      </div>

      {/* Reason for return */}
      {latestReturnEntry?.comment && (
        <div className="flex gap-3 p-4 bg-orange-50 rounded-xl border border-orange-200">
          <RotateCcw className="w-5 h-5 text-orange-600 shrink-0 mt-0.5" />
          <div className="text-sm flex-1">
            <div className="font-medium text-orange-900 mb-1">Reason for return</div>
            <p className="text-orange-800 whitespace-pre-wrap">{latestReturnEntry.comment}</p>
            {latestReturnEntry.changed_by && (
              <div className="text-xs text-orange-700 mt-2">
                — {latestReturnEntry.changed_by.name}
                {' · '}
                {format(new Date(latestReturnEntry.changed_at ?? latestReturnEntry.created_at), 'dd MMM yyyy, HH:mm')}
              </div>
            )}
          </div>
        </div>
      )}

      {/* Form-type instructions */}
      {formType.instructions && (
        <div className="flex gap-3 p-4 bg-blue-50 rounded-xl border border-blue-100">
          <FileText className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" />
          <p className="text-sm text-blue-700">{formType.instructions}</p>
        </div>
      )}

      {/* Existing documents (read-only summary) */}
      {submission.documents && submission.documents.length > 0 && (
        <div className="p-4 bg-gray-50 rounded-xl border border-gray-100">
          <div className="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">
            Already attached
          </div>
          <ul className="space-y-1">
            {submission.documents.map(d => (
              <li key={d.id} className="flex items-center gap-2 text-sm text-gray-700">
                <Paperclip className="w-3.5 h-3.5 text-gray-400 shrink-0" />
                <span className="truncate">{d.original_name ?? d.file_name}</span>
                <span className="text-xs text-gray-400">({d.size_human})</span>
              </li>
            ))}
          </ul>
          <p className="text-xs text-gray-500 mt-2">
            Existing files are kept. You only need to upload new ones if requested.
          </p>
        </div>
      )}

      {/* Upload progress */}
      {submitState === 'uploading' && (
        <div className="flex items-center gap-3 p-4 bg-brand-50 border border-brand-100 rounded-xl">
          <Spinner className="w-5 h-5 text-brand-500" />
          <p className="text-sm text-brand-700">{uploadProgress}</p>
        </div>
      )}

      {/* Form */}
      <Card>
        <CardBody>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">

            {formType.fields && formType.fields.length > 0 ? (
              formType.fields
                .filter(f => f.is_active && f.field_type !== 'file')
                .map((field) => {
                  const reg = register(field.field_key, {
                    required: field.is_required ? `${field.label} is required` : false,
                  });
                  const err = errors[field.field_key]?.message as string | undefined;

                  if (field.field_type === 'textarea') {
                    return (
                      <Textarea
                        key={field.id}
                        label={field.label}
                        placeholder={field.placeholder ?? ''}
                        required={field.is_required}
                        error={err}
                        hint={field.help_text ?? undefined}
                        {...reg}
                      />
                    );
                  }
                  if (field.field_type === 'select' && field.options) {
                    return (
                      <Select
                        key={field.id}
                        label={field.label}
                        options={[
                          { value: '', label: '— Select —' },
                          ...field.options.map(o => ({ value: o, label: o })),
                        ]}
                        required={field.is_required}
                        {...reg}
                      />
                    );
                  }
                  if (field.field_type === 'checkbox') {
                    return (
                      <label key={field.id} className="flex items-start gap-3 cursor-pointer">
                        <input
                          type="checkbox"
                          className="mt-1 accent-brand-500"
                          {...register(field.field_key)}
                        />
                        <div>
                          <div className="text-sm font-medium text-gray-700">
                            {field.label}
                            {field.is_required && <span className="text-red-500 ml-1">*</span>}
                          </div>
                          {field.help_text && (
                            <div className="text-xs text-gray-400 mt-0.5">{field.help_text}</div>
                          )}
                        </div>
                      </label>
                    );
                  }
                  const typeMap: Record<string, string> = {
                    email: 'email', phone: 'tel', date: 'date', number: 'number',
                  };
                  return (
                    <Input
                      key={field.id}
                      label={field.label}
                      type={typeMap[field.field_type] ?? 'text'}
                      placeholder={field.placeholder ?? ''}
                      required={field.is_required}
                      error={err}
                      hint={field.help_text ?? undefined}
                      {...reg}
                    />
                  );
                })
            ) : (
              /* Fallback: no schema — free-text description */
              <Textarea
                label="Description / Details"
                placeholder="Describe your request..."
                required
                hint="Provide as much detail as possible."
                {...register('description', { required: 'Please describe your request.' })}
                error={errors.description?.message as string}
              />
            )}

            {/* New file uploads — optional. Existing docs are listed above. */}
            {hasFiles && (
              <div className="space-y-3 pt-2">
                <div className="text-xs font-medium text-gray-500 uppercase tracking-wide">
                  Attach replacement / additional files (optional)
                </div>
                {fileFields.map(field => (
                  <div key={field.id}>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      {field.label}
                      {field.is_required && <span className="text-red-500 ml-1">*</span>}
                    </label>
                    <div className="flex items-center gap-3 p-3 border border-dashed border-gray-300 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors">
                      <Paperclip className="w-4 h-4 text-gray-400 shrink-0" />
                      <input
                        type="file"
                        ref={el => { fileRefs.current[field.field_key] = el; }}
                        className="text-sm text-gray-600 file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                      />
                    </div>
                    {field.help_text && (
                      <p className="mt-1 text-xs text-gray-400">{field.help_text}</p>
                    )}
                  </div>
                ))}
              </div>
            )}

            {hasFiles && (
              <div className="text-xs text-brand-600 bg-brand-50 border border-brand-100 rounded-lg px-3 py-2 flex items-start gap-2">
                <CheckCircle className="w-3.5 h-3.5 mt-0.5 shrink-0" />
                Any files you attach above will be added to your submission. Existing documents are kept.
              </div>
            )}

            <div className="flex gap-3 pt-2">
              <Button type="submit" loading={isBusy} className="flex-1">
                {submitState === 'uploading' ? 'Uploading files…' : 'Resubmit'}
              </Button>
              <Link href={`/submissions/${ref}`}>
                <Button type="button" variant="outline" disabled={isBusy}>Cancel</Button>
              </Link>
            </div>
          </form>
        </CardBody>
      </Card>
    </div>
  );
}
