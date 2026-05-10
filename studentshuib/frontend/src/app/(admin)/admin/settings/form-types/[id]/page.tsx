'use client';
import { useState, useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { superApi } from '@/lib/api';
import { Card, Button, Input, Spinner, useToast } from '@/components/ui';
import type { FormType } from '@/types';
import {
  ArrowLeft, Plus, Pencil, Trash2, ChevronUp, ChevronDown,
  GripVertical, Type, AlignLeft, List, Calendar, Mail,
  Phone, Hash, Paperclip, ToggleLeft,
} from 'lucide-react';
import Link from 'next/link';
import { clsx } from 'clsx';
import { toFieldKey } from '@/lib/utils';

// ---- Types ----------------------------------------------------------------

interface FormField {
  id: number;
  form_type_id: number;
  label: string;
  field_key: string;
  field_type: FieldType;
  options: string[] | null;
  is_required: boolean;
  placeholder: string | null;
  help_text: string | null;
  sort_order: number;
  is_active: boolean;
}

type FieldType = 'text' | 'textarea' | 'select' | 'checkbox' | 'date' | 'email' | 'phone' | 'number' | 'file';

interface FieldForm {
  label: string;
  field_key: string;
  field_type: FieldType;
  options: string;      // textarea — one option per line
  is_required: boolean;
  placeholder: string;
  help_text: string;
  is_active: boolean;
}

const EMPTY_FIELD: FieldForm = {
  label: '', field_key: '', field_type: 'text',
  options: '', is_required: false,
  placeholder: '', help_text: '', is_active: true,
};

const FIELD_TYPE_OPTS: { value: FieldType; label: string; Icon: React.ElementType }[] = [
  { value: 'text',     label: 'Short text',   Icon: Type        },
  { value: 'textarea', label: 'Long text',    Icon: AlignLeft   },
  { value: 'select',   label: 'Dropdown',     Icon: List        },
  { value: 'checkbox', label: 'Checkbox',     Icon: ToggleLeft  },
  { value: 'date',     label: 'Date',         Icon: Calendar    },
  { value: 'email',    label: 'Email',        Icon: Mail        },
  { value: 'phone',    label: 'Phone',        Icon: Phone       },
  { value: 'number',   label: 'Number',       Icon: Hash        },
  { value: 'file',     label: 'File upload',  Icon: Paperclip   },
];

const TYPE_BADGE: Record<FieldType, string> = {
  text:     'bg-gray-100 text-gray-600',
  textarea: 'bg-blue-50 text-blue-700',
  select:   'bg-purple-50 text-purple-700',
  checkbox: 'bg-teal-50 text-teal-700',
  date:     'bg-orange-50 text-orange-700',
  email:    'bg-pink-50 text-pink-700',
  phone:    'bg-yellow-50 text-yellow-700',
  number:   'bg-indigo-50 text-indigo-700',
  file:     'bg-green-50 text-green-700',
};


// ---- Page -----------------------------------------------------------------

export default function FormTypeFieldsPage() {
  const { id }     = useParams<{ id: string }>();
  const formTypeId = parseInt(id, 10);
  const qc         = useQueryClient();
  const { toast }  = useToast();

  const [editingId, setEditingId]   = useState<number | null>(null);
  const [showAdd,   setShowAdd]     = useState(false);
  const [fieldForm, setFieldForm]   = useState<FieldForm>(EMPTY_FIELD);
  const [keyEdited, setKeyEdited]   = useState(false);  // track if user manually edited field_key

  // ---- Queries -----

  const { data: formType, isLoading: ftLoading } = useQuery({
    queryKey: ['form-type-detail', formTypeId],
    queryFn:  () => superApi.formType(formTypeId),
    select:   (r) => r.data.form_type as FormType & { fields: FormField[] },
  });

  const { data: fields = [], isLoading: fieldsLoading } = useQuery({
    queryKey: ['form-fields', formTypeId],
    queryFn:  () => superApi.formTypeFields(formTypeId),
    select:   (r) => (r.data.fields as FormField[]).sort((a, b) => a.sort_order - b.sort_order),
  });

  // ---- Mutations ----

  const createMut = useMutation({
    mutationFn: (data: Record<string, unknown>) => superApi.createField(formTypeId, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['form-fields', formTypeId] });
      setShowAdd(false);
      setFieldForm(EMPTY_FIELD);
      setKeyEdited(false);
      toast('Field added.', 'success');
    },
    onError: (err: unknown) => {
      const msgs = (err as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data?.errors;
      toast(msgs ? Object.values(msgs).flat().join(' ') : 'Failed to add field.', 'error');
    },
  });

  const updateMut = useMutation({
    mutationFn: ({ fid, data }: { fid: number; data: Record<string, unknown> }) =>
      superApi.updateField(formTypeId, fid, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['form-fields', formTypeId] });
      setEditingId(null);
      toast('Field updated.', 'success');
    },
    onError: (err: unknown) => {
      const msgs = (err as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data?.errors;
      toast(msgs ? Object.values(msgs).flat().join(' ') : 'Failed to update field.', 'error');
    },
  });

  const deleteMut = useMutation({
    mutationFn: (fid: number) => superApi.deleteField(formTypeId, fid),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['form-fields', formTypeId] });
      toast('Field deleted.', 'success');
    },
    onError: () => toast('Failed to delete field.', 'error'),
  });

  const reorderMut = useMutation({
    mutationFn: (order: number[]) => superApi.reorderFields(formTypeId, order),
    onSuccess: (res) => {
      qc.setQueryData(['form-fields', formTypeId], res);
    },
  });

  // ---- Helpers ----

  const handleLabelChange = (value: string) => {
    setFieldForm(f => ({
      ...f,
      label: value,
      field_key: keyEdited ? f.field_key : toFieldKey(value),
    }));
  };

  const handleFieldKeyChange = (value: string) => {
    setKeyEdited(true);
    setFieldForm(f => ({ ...f, field_key: value }));
  };

  const parseOptions = (raw: string): string[] =>
    raw.split('\n').map(s => s.trim()).filter(Boolean);

  const toPayload = (form: FieldForm): Record<string, unknown> => ({
    label:       form.label,
    field_key:   form.field_key,
    field_type:  form.field_type,
    options:     form.field_type === 'select' ? parseOptions(form.options) : null,
    is_required: form.is_required,
    placeholder: form.placeholder || null,
    help_text:   form.help_text   || null,
    is_active:   form.is_active,
  });

  const startEdit = (field: FormField) => {
    setShowAdd(false);
    setEditingId(field.id);
    setKeyEdited(true);
    setFieldForm({
      label:       field.label,
      field_key:   field.field_key,
      field_type:  field.field_type,
      options:     (field.options ?? []).join('\n'),
      is_required: field.is_required,
      placeholder: field.placeholder ?? '',
      help_text:   field.help_text   ?? '',
      is_active:   field.is_active,
    });
  };

  const cancelForm = () => {
    setShowAdd(false);
    setEditingId(null);
    setFieldForm(EMPTY_FIELD);
    setKeyEdited(false);
  };

  const handleSave = () => {
    const payload = toPayload(fieldForm);
    if (editingId !== null) updateMut.mutate({ fid: editingId, data: payload });
    else                    createMut.mutate(payload);
  };

  // Move a field up or down by swapping its sort position
  const moveField = (fieldId: number, direction: 'up' | 'down') => {
    const sorted = [...fields].sort((a, b) => a.sort_order - b.sort_order);
    const idx    = sorted.findIndex(f => f.id === fieldId);
    if (direction === 'up'   && idx === 0)                  return;
    if (direction === 'down' && idx === sorted.length - 1)  return;

    const swapIdx = direction === 'up' ? idx - 1 : idx + 1;
    const next    = [...sorted];
    [next[idx], next[swapIdx]] = [next[swapIdx], next[idx]];
    reorderMut.mutate(next.map(f => f.id));
  };

  // ---- Render ---------------------------------------------------------------

  const isLoading = ftLoading || fieldsLoading;
  const isSaving  = createMut.isPending || updateMut.isPending;

  return (
    <div className="p-6 space-y-5 max-w-4xl">

      {/* Header */}
      <div className="flex items-center gap-3">
        <Link
          href="/admin/settings/form-types"
          className="text-gray-400 hover:text-gray-600 transition-colors"
        >
          <ArrowLeft className="w-5 h-5" />
        </Link>
        <div className="flex-1 min-w-0">
          <h1 className="text-xl font-bold text-gray-900 truncate">
            {ftLoading ? '…' : formType?.name ?? 'Form Type'}
          </h1>
          <p className="text-sm text-gray-400">Field builder</p>
        </div>
        <Button size="sm" onClick={() => { cancelForm(); setShowAdd(true); }}>
          <Plus className="w-4 h-4" /> Add Field
        </Button>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-20"><Spinner /></div>
      ) : (
        <>
          {/* Field list */}
          {fields.length === 0 && !showAdd ? (
            <Card>
              <div className="py-16 text-center text-gray-400 text-sm">
                No fields yet. Click "Add Field" to define what students fill in when submitting this form.
              </div>
            </Card>
          ) : (
            <Card>
              <ul className="divide-y divide-gray-50">
                {fields.map((field, idx) => {
                  const TypeIcon = FIELD_TYPE_OPTS.find(o => o.value === field.field_type)?.Icon ?? Type;
                  return (
                    <li key={field.id} className={clsx('px-5 py-4', !field.is_active && 'opacity-50')}>
                      {editingId === field.id ? (
                        /* ---- Inline edit form ---- */
                        <FieldFormPanel
                          form={fieldForm}
                          isSaving={isSaving}
                          onLabelChange={handleLabelChange}
                          onFieldKeyChange={handleFieldKeyChange}
                          onChange={setFieldForm}
                          onSave={handleSave}
                          onCancel={cancelForm}
                        />
                      ) : (
                        /* ---- Row view ---- */
                        <div className="flex items-center gap-3">
                          <GripVertical className="w-4 h-4 text-gray-300 shrink-0" />

                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2 flex-wrap">
                              <span className="text-sm font-medium text-gray-800">{field.label}</span>
                              {field.is_required && (
                                <span className="text-red-500 text-xs font-bold">*</span>
                              )}
                              <span className={clsx('inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium', TYPE_BADGE[field.field_type])}>
                                <TypeIcon className="w-3 h-3" />
                                {FIELD_TYPE_OPTS.find(o => o.value === field.field_type)?.label}
                              </span>
                            </div>
                            <div className="text-xs text-gray-400 font-mono mt-0.5">{field.field_key}</div>
                            {field.field_type === 'select' && field.options && (
                              <div className="text-xs text-gray-400 mt-0.5 truncate">
                                Options: {field.options.join(', ')}
                              </div>
                            )}
                          </div>

                          <div className="flex items-center gap-1 shrink-0">
                            <button
                              onClick={() => moveField(field.id, 'up')}
                              disabled={idx === 0}
                              className="p-1 text-gray-300 hover:text-gray-600 disabled:opacity-20 transition-colors"
                              title="Move up"
                            >
                              <ChevronUp className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => moveField(field.id, 'down')}
                              disabled={idx === fields.length - 1}
                              className="p-1 text-gray-300 hover:text-gray-600 disabled:opacity-20 transition-colors"
                              title="Move down"
                            >
                              <ChevronDown className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => startEdit(field)}
                              className="p-1.5 text-gray-400 hover:text-brand-500 transition-colors"
                              title="Edit field"
                            >
                              <Pencil className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => {
                                if (confirm(`Delete field "${field.label}"? This cannot be undone.`)) {
                                  deleteMut.mutate(field.id);
                                }
                              }}
                              className="p-1.5 text-gray-400 hover:text-red-500 transition-colors"
                              title="Delete field"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        </div>
                      )}
                    </li>
                  );
                })}
              </ul>
            </Card>
          )}

          {/* Add field panel */}
          {showAdd && (
            <Card>
              <div className="px-5 py-3 border-b border-gray-100">
                <h3 className="text-sm font-semibold text-gray-800">New field</h3>
              </div>
              <div className="p-5">
                <FieldFormPanel
                  form={fieldForm}
                  isSaving={isSaving}
                  onLabelChange={handleLabelChange}
                  onFieldKeyChange={handleFieldKeyChange}
                  onChange={setFieldForm}
                  onSave={handleSave}
                  onCancel={cancelForm}
                />
              </div>
            </Card>
          )}
        </>
      )}
    </div>
  );
}

// ---- Shared field edit/add form -------------------------------------------

function FieldFormPanel({
  form,
  isSaving,
  onLabelChange,
  onFieldKeyChange,
  onChange,
  onSave,
  onCancel,
}: {
  form: FieldForm;
  isSaving: boolean;
  onLabelChange: (v: string) => void;
  onFieldKeyChange: (v: string) => void;
  onChange: (updater: (prev: FieldForm) => FieldForm) => void;
  onSave: () => void;
  onCancel: () => void;
}) {
  return (
    <div className="space-y-4">
      <div className="grid sm:grid-cols-2 gap-4">
        {/* Label */}
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">
            Label <span className="text-red-500">*</span>
          </label>
          <Input
            value={form.label}
            onChange={e => onLabelChange(e.target.value)}
            placeholder="e.g. Purpose of Request"
          />
        </div>

        {/* Field key */}
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">
            Field key <span className="text-red-500">*</span>
            <span className="ml-1 text-gray-300 font-normal">(unique ID, letters/numbers/underscores)</span>
          </label>
          <Input
            value={form.field_key}
            onChange={e => onFieldKeyChange(e.target.value)}
            placeholder="purpose_of_request"
            className="font-mono text-sm"
          />
        </div>

        {/* Field type */}
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">
            Field type <span className="text-red-500">*</span>
          </label>
          <select
            value={form.field_type}
            onChange={e => onChange(f => ({ ...f, field_type: e.target.value as FieldType }))}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-500"
          >
            {FIELD_TYPE_OPTS.map(o => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
        </div>

        {/* Placeholder */}
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">Placeholder text</label>
          <Input
            value={form.placeholder}
            onChange={e => onChange(f => ({ ...f, placeholder: e.target.value }))}
            placeholder="Optional hint shown inside the field"
          />
        </div>
      </div>

      {/* Options — only for select type */}
      {form.field_type === 'select' && (
        <div>
          <label className="block text-xs font-medium text-gray-500 mb-1">
            Options <span className="text-red-500">*</span>
            <span className="ml-1 text-gray-300 font-normal">— one per line</span>
          </label>
          <textarea
            rows={4}
            value={form.options}
            onChange={e => onChange(f => ({ ...f, options: e.target.value }))}
            placeholder={"Option A\nOption B\nOption C"}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-brand-500"
          />
        </div>
      )}

      {/* Help text */}
      <div>
        <label className="block text-xs font-medium text-gray-500 mb-1">Help text</label>
        <Input
          value={form.help_text}
          onChange={e => onChange(f => ({ ...f, help_text: e.target.value }))}
          placeholder="Short guidance shown below the field"
        />
      </div>

      {/* Toggles */}
      <div className="flex gap-6">
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
          <input
            type="checkbox"
            checked={form.is_required}
            onChange={e => onChange(f => ({ ...f, is_required: e.target.checked }))}
            className="accent-brand-500"
          />
          Required
        </label>
        <label className="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
          <input
            type="checkbox"
            checked={form.is_active}
            onChange={e => onChange(f => ({ ...f, is_active: e.target.checked }))}
            className="accent-brand-500"
          />
          Active
        </label>
      </div>

      {/* Actions */}
      <div className="flex gap-2 pt-1">
        <Button onClick={onSave} size="sm" loading={isSaving}>
          Save field
        </Button>
        <Button variant="ghost" size="sm" onClick={onCancel}>
          Cancel
        </Button>
      </div>
    </div>
  );
}
