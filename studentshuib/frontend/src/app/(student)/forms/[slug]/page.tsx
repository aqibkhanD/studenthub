'use client';
import { useState, useRef } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { studentApi } from '@/lib/api';
import { Button, Card, CardBody, Input, Textarea, Select, Spinner, useToast } from '@/components/ui';
import type { FormType } from '@/types';
import { ArrowLeft, AlertTriangle, Paperclip, CheckCircle } from 'lucide-react';
import Link from 'next/link';

// Separate file fields from JSON-serialisable fields before submitting.
// File inputs cannot be serialised to JSON — they are uploaded as documents
// in a second step after the submission is created.

export default function SubmitFormPage() {
  const { slug }  = useParams<{ slug: string }>();
  const router    = useRouter();
  const { toast } = useToast();

  const [isAnonymous, setIsAnonymous] = useState(false);
  const [submitState, setSubmitState] = useState<'idle' | 'submitting' | 'uploading' | 'done'>('idle');
  const [uploadProgress, setUploadProgress] = useState('');

  // File inputs are controlled separately — not via react-hook-form
  const fileRefs = useRef<Record<string, HTMLInputElement | null>>({});

  const { data: formType, isLoading } = useQuery({
    queryKey: ['form-type', slug],
    queryFn:  () => studentApi.formType(slug),
    select:   (res) => res.data.form_type as FormType,
  });

  const { register, handleSubmit, formState: { errors } } = useForm<Record<string, string>>();

  const onSubmit = async (formData: Record<string, string>) => {
    setSubmitState('submitting');

    // Split fields: file-type fields handled separately
    const fileFields  = (formType?.fields ?? []).filter(f => f.field_type === 'file' && f.is_active);
    const textFields  = (formType?.fields ?? []).filter(f => f.field_type !== 'file');

    // Build text-only form_data (file fields excluded from JSON payload)
    const textData: Record<string, string> = {};
    textFields.forEach(f => {
      if (formData[f.field_key] !== undefined) textData[f.field_key] = formData[f.field_key];
    });
    // If no dynamic fields, include the raw formData as-is (fallback description field)
    const payload = (formType?.fields?.length ?? 0) === 0 ? formData : textData;

    try {
      const res = await studentApi.submit({
        form_type_id: formType!.id,
        is_anonymous: isAnonymous,
        form_data:    payload,
        submit:       true,
      });

      const ref = res.data.submission.reference_no;

      // Step 2: upload any selected files as documents
      if (fileFields.length > 0) {
        setSubmitState('uploading');
        let uploaded = 0;

        for (const field of fileFields) {
          const input = fileRefs.current[field.field_key];
          const file  = input?.files?.[0];
          if (!file) continue;

          setUploadProgress(`Uploading ${field.label} (${uploaded + 1}/${fileFields.length})…`);

          const fd = new FormData();
          fd.append('document', file);
          fd.append('description', field.label);

          try {
            await studentApi.uploadDocument(ref, fd);
            uploaded++;
          } catch {
            // Non-fatal — submission exists; user can upload documents from the detail page
            toast(`Could not upload "${field.label}". You can add it from the submission page.`, 'error');
          }
        }
      }

      setSubmitState('done');
      toast(`Request submitted! Reference: ${ref}`, 'success');
      router.push(`/student/submissions/${ref}`);
    } catch (err: unknown) {
      setSubmitState('idle');
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast(msg || 'Submission failed. Please try again.', 'error');
    }
  };

  if (isLoading) return <div className="flex justify-center py-24"><Spinner /></div>;
  if (!formType) return <div className="p-6 text-gray-500">Form type not found.</div>;

  const isLoading2 = submitState === 'submitting' || submitState === 'uploading';
  const fileFields  = (formType.fields ?? []).filter(f => f.field_type === 'file' && f.is_active);
  const hasFiles    = fileFields.length > 0;

  return (
    <div className="p-6 max-w-2xl mx-auto space-y-6">
      <div className="flex items-center gap-3">
        <Link href="/student/forms" className="text-gray-400 hover:text-gray-600">
          <ArrowLeft className="w-5 h-5" />
        </Link>
        <div>
          <h1 className="text-xl font-bold text-gray-900">{formType.name}</h1>
          <p className="text-sm text-gray-500">{formType.department?.name}</p>
        </div>
      </div>

      {/* Instructions */}
      {formType.instructions && (
        <div className="flex gap-3 p-4 bg-blue-50 rounded-xl border border-blue-100">
          <AlertTriangle className="w-5 h-5 text-blue-500 shrink-0 mt-0.5" />
          <p className="text-sm text-blue-700">{formType.instructions}</p>
        </div>
      )}

      {/* Upload progress overlay */}
      {submitState === 'uploading' && (
        <div className="flex items-center gap-3 p-4 bg-brand-50 border border-brand-100 rounded-xl">
          <Spinner className="w-5 h-5 text-brand-500" />
          <p className="text-sm text-brand-700">{uploadProgress}</p>
        </div>
      )}

      <Card>
        <CardBody>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">

            {/* Dynamic fields — file-type fields rendered separately below */}
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
              /* Fallback: no dynamic fields defined — show a free-text description field */
              <Textarea
                label="Description / Details"
                placeholder="Describe your request..."
                required
                hint="Provide as much detail as possible."
                {...register('description', { required: 'Please describe your request.' })}
                error={errors.description?.message as string}
              />
            )}

            {/* File upload fields — controlled outside react-hook-form */}
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

            {/* Anonymous toggle */}
            {formType.allow_anonymous && (
              <label className="flex items-center gap-3 cursor-pointer p-3 rounded-lg bg-purple-50 border border-purple-100">
                <input
                  type="checkbox"
                  checked={isAnonymous}
                  onChange={e => setIsAnonymous(e.target.checked)}
                  className="w-4 h-4 accent-brand-500"
                />
                <div>
                  <div className="text-sm font-medium text-purple-800">Submit anonymously</div>
                  <div className="text-xs text-purple-600">
                    Your identity will not be disclosed to the reviewing department.
                  </div>
                </div>
              </label>
            )}

            {/* File upload note */}
            {hasFiles && (
              <div className="text-xs text-brand-600 bg-brand-50 border border-brand-100 rounded-lg px-3 py-2 flex items-start gap-2">
                <CheckCircle className="w-3.5 h-3.5 mt-0.5 shrink-0" />
                Files are uploaded securely after your form is submitted. If an upload fails you can re-attach from the submission detail page.
              </div>
            )}

            {formType.requires_documents && !hasFiles && (
              <div className="text-xs text-orange-600 bg-orange-50 border border-orange-100 rounded-lg px-3 py-2">
                This form requires supporting documents. You can upload files from the submission detail page after submitting.
              </div>
            )}

            <div className="flex gap-3 pt-2">
              <Button type="submit" loading={isLoading2} className="flex-1">
                {submitState === 'uploading' ? 'Uploading files…' : 'Submit Request'}
              </Button>
              <Link href="/student/forms">
                <Button type="button" variant="outline" disabled={isLoading2}>Cancel</Button>
              </Link>
            </div>
          </form>
        </CardBody>
      </Card>
    </div>
  );
}
