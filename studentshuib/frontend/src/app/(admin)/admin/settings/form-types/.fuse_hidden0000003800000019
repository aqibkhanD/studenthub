'use client';
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { superApi } from '@/lib/api';
import { Card, Button, Input, Select, Spinner, EmptyState, useToast } from '@/components/ui';
import type { FormType, Department } from '@/types';
import { CATEGORY_LABELS } from '@/types';
import { FileText, Plus, Pencil, ToggleLeft, ToggleRight, Settings2 } from 'lucide-react';
import Link from 'next/link';
import { clsx } from 'clsx';

type FTForm = {
  name: string; slug: string; category: string; department_id: string;
  description: string; instructions: string; sla_hours: string;
  requires_documents: boolean; allow_anonymous: boolean;
};
const EMPTY: FTForm = {
  name: '', slug: '', category: '', department_id: '',
  description: '', instructions: '', sla_hours: '',
  requires_documents: false, allow_anonymous: false,
};

const CATEGORY_OPTS = [
  { value: '', label: 'Select category' },
  ...Object.entries(CATEGORY_LABELS).map(([v, l]) => ({ value: v, label: l })),
];

export default function FormTypesSettingsPage() {
  const qc = useQueryClient();
  const { toast } = useToast();

  const [showForm, setShowForm] = useState(false);
  const [editId,   setEditId]   = useState<number | null>(null);
  const [form,     setForm]     = useState<FTForm>(EMPTY);
  const [filter,   setFilter]   = useState('');

  // Backend returns Laravel paginated { data: [...], current_page, total, ... }
  const { data: formTypes, isLoading } = useQuery({
    queryKey: ['form-types-admin'],
    queryFn:  () => superApi.formTypes(),
    select:   (res) => ((res.data as { data?: FormType[] })?.data ?? []) as FormType[],
  });

  // Backend returns { departments: [...] } — keyed object, not paginated.
  const { data: depts } = useQuery({
    queryKey: ['departments'],
    queryFn:  () => superApi.departments(),
    select:   (res) => ((res.data as { departments?: Department[] })?.departments ?? []) as Department[],
  });

  const deptOpts = [
    { value: '', label: 'Select department' },
    ...(depts ?? []).map(d => ({ value: String(d.id), label: d.name })),
  ];

  // Convert FTForm into a backend-ready payload. Empty numeric strings become
  // null so Laravel's `nullable|integer` rule passes (it rejects "" because
  // empty string is not null).
  const toPayload = (f: FTForm): Record<string, unknown> => ({
    name:               f.name,
    slug:               f.slug,
    category:           f.category,
    department_id:      f.department_id === '' ? null : Number(f.department_id),
    description:        f.description || null,
    instructions:       f.instructions || null,
    sla_hours:          f.sla_hours === '' ? null : Number(f.sla_hours),
    requires_documents: f.requires_documents,
    allow_anonymous:    f.allow_anonymous,
  });

  const showApiError = (err: unknown, fallback: string) => {
    const data = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data;
    if (data?.errors) {
      toast({ title: Object.values(data.errors).flat().join(' '), variant: 'error' });
    } else if (data?.message) {
      toast({ title: data.message, variant: 'error' });
    } else {
      toast({ title: fallback, variant: 'error' });
    }
  };

  const createMut = useMutation({
    mutationFn: (data: FTForm) => superApi.createFormType(toPayload(data)),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['form-types-admin'] });
      setShowForm(false); setForm(EMPTY);
      toast({ title: 'Form type created', variant: 'success' });
    },
    onError: (err) => showApiError(err, 'Failed to create form type'),
  });

  const updateMut = useMutation({
    mutationFn: ({ id, data }: { id: number; data: FTForm }) =>
      superApi.updateFormType(id, toPayload(data)),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['form-types-admin'] });
      setEditId(null);
      toast({ title: 'Form type updated', variant: 'success' });
    },
    onError: (err) => showApiError(err, 'Failed to update form type'),
  });

  const toggleMut = useMutation({
    mutationFn: (id: number) => superApi.toggleFormType(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['form-types-admin'] }),
    onError:   () => toast({ title: 'Failed to toggle', variant: 'error' }),
  });

  const startEdit = (ft: FormType) => {
    setEditId(ft.id);
    setForm({
      name: ft.name, slug: ft.slug, category: ft.category,
      department_id: String(ft.department_id ?? ''),
      description: ft.description ?? '', instructions: ft.instructions ?? '',
      sla_hours: String(ft.sla_hours ?? ''),
      requires_documents: ft.requires_documents ?? false,
      allow_anonymous:   ft.allow_anonymous ?? false,
    });
  };

  const handleSubmit = () => {
    if (editId) updateMut.mutate({ id: editId, data: form });
    else        createMut.mutate(form);
  };

  const displayed = (formTypes ?? []).filter(ft => !filter || ft.category === filter);

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-center justify-between gap-3 flex-wrap">
        <h1 className="text-xl font-bold text-gray-900">Form Types</h1>
        <div className="flex gap-2">
          <select
            className="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-500"
            value={filter}
            onChange={e => setFilter(e.target.value)}
          >
            <option value="">All categories</option>
            {Object.entries(CATEGORY_LABELS).map(([v, l]) => (
              <option key={v} value={v}>{l}</option>
            ))}
          </select>
          <Button size="sm" onClick={() => { setShowForm(true); setEditId(null); setForm(EMPTY); }}>
            <Plus className="w-4 h-4" /> Add Form Type
          </Button>
        </div>
      </div>

      {/* Form panel */}
      {(showForm || editId !== null) && (
        <Card>
          <div className="px-6 py-4 border-b border-gray-100">
            <h2 className="text-sm font-semibold text-gray-900">{editId ? 'Edit Form Type' : 'New Form Type'}</h2>
          </div>
          <div className="p-6 grid sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Name *</label>
              <Input value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} placeholder="e.g. Bonafide Certificate" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Slug *</label>
              <Input value={form.slug} onChange={e => setForm(f => ({ ...f, slug: e.target.value }))} placeholder="bonafide-certificate" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Category *</label>
              <Select options={CATEGORY_OPTS} value={form.category} onChange={e => setForm(f => ({ ...f, category: e.target.value }))} />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Department *</label>
              <Select options={deptOpts} value={form.department_id} onChange={e => setForm(f => ({ ...f, department_id: e.target.value }))} />
            </div>
            <div className="sm:col-span-2">
              <label className="block text-xs font-medium text-gray-500 mb-1">Description</label>
              <Input value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} placeholder="Short description visible to students" />
            </div>
            <div className="sm:col-span-2">
              <label className="block text-xs font-medium text-gray-500 mb-1">Instructions</label>
              <textarea
                rows={3}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-brand-500"
                placeholder="Detailed instructions for the student..."
                value={form.instructions}
                onChange={e => setForm(f => ({ ...f, instructions: e.target.value }))}
              />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">SLA Override (hours)</label>
              <Input type="number" value={form.sla_hours} onChange={e => setForm(f => ({ ...f, sla_hours: e.target.value }))} placeholder="Leave blank to use dept default" />
            </div>
            <div className="flex gap-6 items-end pb-1">
              <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" className="accent-brand-500" checked={form.requires_documents} onChange={e => setForm(f => ({ ...f, requires_documents: e.target.checked }))} />
                Requires documents
              </label>
              <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" className="accent-brand-500" checked={form.allow_anonymous} onChange={e => setForm(f => ({ ...f, allow_anonymous: e.target.checked }))} />
                Allows anonymous
              </label>
            </div>
          </div>
          <div className="px-6 pb-5 flex gap-2">
            <Button onClick={handleSubmit} loading={createMut.isPending || updateMut.isPending}>
              {editId ? 'Save Changes' : 'Create Form Type'}
            </Button>
            <Button variant="ghost" onClick={() => { setShowForm(false); setEditId(null); }}>Cancel</Button>
          </div>
        </Card>
      )}

      <Card>
        {isLoading ? (
          <div className="flex justify-center py-16"><Spinner /></div>
        ) : displayed.length === 0 ? (
          <EmptyState title="No form types" description="Add your first form type." icon={FileText} />
        ) : (
          <>
            <div className="hidden md:grid grid-cols-12 gap-4 px-6 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
              <div className="col-span-4">Name</div>
              <div className="col-span-3">Category</div>
              <div className="col-span-2">Department</div>
              <div className="col-span-1 text-right">Fields</div>
              <div className="col-span-1 text-right">SLA</div>
              <div className="col-span-1" />
            </div>
            <ul className="divide-y divide-gray-50">
              {displayed.map((ft) => {
                const fieldCount = ft.fields?.length ?? 0;
                return (
                <li key={ft.id} className={clsx('grid md:grid-cols-12 gap-4 px-6 py-4 items-center hover:bg-gray-50 transition-colors', !ft.is_active && 'opacity-50')}>
                  <div className="md:col-span-4">
                    <div className="text-sm font-medium text-gray-800">{ft.name}</div>
                    <div className="text-xs text-gray-400 font-mono mt-0.5">{ft.slug}</div>
                  </div>
                  <div className="md:col-span-3 text-sm text-gray-500">{CATEGORY_LABELS[ft.category as keyof typeof CATEGORY_LABELS] ?? ft.category}</div>
                  <div className="md:col-span-2 text-sm text-gray-500">{ft.department?.name ?? '—'}</div>
                  <div className="md:col-span-1 text-right">
                    {/* Field-count chip — orange when 0 (form has no schema, will be empty for students) */}
                    <span className={clsx(
                      'inline-flex items-center justify-center text-xs font-medium px-2 py-0.5 rounded-full',
                      fieldCount === 0
                        ? 'bg-orange-50 text-orange-600'
                        : 'bg-gray-100 text-gray-600',
                    )} title={fieldCount === 0 ? 'No fields defined — students will see only the description' : undefined}>
                      {fieldCount}
                    </span>
                  </div>
                  <div className="md:col-span-1 text-sm text-gray-400 text-right">{ft.sla_hours ? `${ft.sla_hours}h` : '—'}</div>
                  <div className="md:col-span-1 flex justify-end gap-2">
                    <Link
                      href={`/admin/settings/form-types/${ft.id}`}
                      className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-brand-600 bg-brand-50 rounded-md hover:bg-brand-100 transition-colors"
                      title="Manage fields"
                    >
                      <Settings2 className="w-3.5 h-3.5" />
                      Fields
                    </Link>
                    <button onClick={() => startEdit(ft)} className="text-gray-400 hover:text-brand-500" title="Edit">
                      <Pencil className="w-4 h-4" />
                    </button>
                    <button onClick={() => toggleMut.mutate(ft.id)} className={clsx('transition-colors', ft.is_active ? 'text-green-500 hover:text-red-500' : 'text-gray-300 hover:text-green-500')} title={ft.is_active ? 'Deactivate' : 'Activate'}>
                      {ft.is_active ? <ToggleRight className="w-5 h-5" /> : <ToggleLeft className="w-5 h-5" />}
                    </button>
                  </div>
                </li>
              );})}
            </ul>
          </>
        )}
      </Card>
    </div>
  );
}
