'use client';
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { superApi } from '@/lib/api';
import { Card, Button, Input, Spinner, EmptyState, useToast } from '@/components/ui';
import type { Department } from '@/types';
import { Building2, Plus, Pencil, X, Check } from 'lucide-react';

type DeptForm = { name: string; code: string; sla_hours: string; email: string };
const EMPTY: DeptForm = { name: '', code: '', sla_hours: '72', email: '' };

export default function DepartmentsSettingsPage() {
  const qc = useQueryClient();
  const { toast } = useToast();

  const [showForm, setShowForm] = useState(false);
  const [editId,   setEditId]   = useState<number | null>(null);
  const [form,     setForm]     = useState<DeptForm>(EMPTY);

  const { data: depts, isLoading } = useQuery({
    queryKey: ['departments'],
    queryFn:  () => superApi.departments(),
    select:   (res) => res.data as Department[],
  });

  const createMut = useMutation({
    mutationFn: (data: DeptForm) => superApi.createDepartment(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['departments'] });
      setShowForm(false);
      setForm(EMPTY);
      toast({ title: 'Department created', variant: 'success' });
    },
    onError: () => toast({ title: 'Failed to create department', variant: 'error' }),
  });

  const updateMut = useMutation({
    mutationFn: ({ id, data }: { id: number; data: DeptForm }) => superApi.updateDepartment(id, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['departments'] });
      setEditId(null);
      toast({ title: 'Department updated', variant: 'success' });
    },
    onError: () => toast({ title: 'Failed to update', variant: 'error' }),
  });

  const startEdit = (d: Department) => {
    setEditId(d.id);
    setForm({ name: d.name, code: d.code ?? '', sla_hours: String(d.sla_hours ?? 72), email: d.email ?? '' });
  };

  const handleSubmit = () => {
    if (editId) {
      updateMut.mutate({ id: editId, data: form });
    } else {
      createMut.mutate(form);
    }
  };

  return (
    <div className="p-6 space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-gray-900">Departments</h1>
        <Button size="sm" onClick={() => { setShowForm(true); setEditId(null); setForm(EMPTY); }}>
          <Plus className="w-4 h-4" /> Add Department
        </Button>
      </div>

      {/* Add form */}
      {(showForm || editId !== null) && (
        <Card>
          <div className="px-6 py-4 border-b border-gray-100">
            <h2 className="text-sm font-semibold text-gray-900">{editId ? 'Edit Department' : 'New Department'}</h2>
          </div>
          <div className="p-6 grid sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Name *</label>
              <Input value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} placeholder="e.g. Registrar Office" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Code *</label>
              <Input value={form.code} onChange={e => setForm(f => ({ ...f, code: e.target.value }))} placeholder="e.g. REG" />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">SLA Hours</label>
              <Input type="number" value={form.sla_hours} onChange={e => setForm(f => ({ ...f, sla_hours: e.target.value }))} />
            </div>
            <div>
              <label className="block text-xs font-medium text-gray-500 mb-1">Contact Email</label>
              <Input type="email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} placeholder="dept@diu.edu.bd" />
            </div>
          </div>
          <div className="px-6 pb-5 flex gap-2">
            <Button onClick={handleSubmit} loading={createMut.isPending || updateMut.isPending}>
              {editId ? 'Save Changes' : 'Create Department'}
            </Button>
            <Button variant="ghost" onClick={() => { setShowForm(false); setEditId(null); }}>Cancel</Button>
          </div>
        </Card>
      )}

      <Card>
        {isLoading ? (
          <div className="flex justify-center py-16"><Spinner /></div>
        ) : !depts || depts.length === 0 ? (
          <EmptyState title="No departments" description="Add your first department." icon={Building2} />
        ) : (
          <>
            <div className="hidden md:grid grid-cols-12 gap-4 px-6 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">
              <div className="col-span-4">Name</div>
              <div className="col-span-2">Code</div>
              <div className="col-span-2">SLA (hrs)</div>
              <div className="col-span-3">Email</div>
              <div className="col-span-1" />
            </div>
            <ul className="divide-y divide-gray-50">
              {depts.map((d) => (
                <li key={d.id} className="grid md:grid-cols-12 gap-4 px-6 py-4 items-center hover:bg-gray-50 transition-colors">
                  <div className="md:col-span-4 text-sm font-medium text-gray-800">{d.name}</div>
                  <div className="md:col-span-2 text-sm text-gray-500 font-mono">{d.code ?? '—'}</div>
                  <div className="md:col-span-2 text-sm text-gray-500">{d.sla_hours ?? 72}h</div>
                  <div className="md:col-span-3 text-sm text-gray-500 truncate">{d.email ?? '—'}</div>
                  <div className="md:col-span-1 flex justify-end">
                    <button onClick={() => startEdit(d)} className="text-gray-400 hover:text-brand-500">
                      <Pencil className="w-4 h-4" />
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          </>
        )}
      </Card>
    </div>
  );
}
